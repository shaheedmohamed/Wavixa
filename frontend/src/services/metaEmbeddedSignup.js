/**
 * Meta Embedded Signup (Facebook Login for Business / JavaScript SDK).
 *
 * Loads the Meta JS SDK on demand and launches the Embedded Signup popup via
 * FB.login with a Login for Business config_id. With the
 * `whatsapp_business_app_onboarding` featureType the flow offers customers the
 * Coexistence branch (connect an existing WhatsApp Business app number).
 *
 * No secrets live here: the exchangeable `code` is sent to the Laravel backend,
 * which performs the server-to-server token exchange.
 */

const SDK_NAMESPACE = 'wavixa_fb_sdk';
const META_ORIGINS = ['https://www.facebook.com', 'https://web.facebook.com'];

let sdkPromise = null;

/**
 * Inject and initialise the Facebook JavaScript SDK exactly once.
 * config_id is passed at init time too: in Chrome the SDK may route the
 * dialog through FedCM, which reads the business login configuration from
 * init rather than from the FB.login options.
 */
export function loadFacebookSdk(appId, apiVersion, configId) {
  if (sdkPromise) {
    return sdkPromise;
  }

  sdkPromise = new Promise((resolve, reject) => {
    if (window.FB) {
      resolve(window.FB);
      return;
    }

    window[SDK_NAMESPACE] = window[SDK_NAMESPACE] || {};
    window.fbAsyncInit = function () {
      window.FB.init({
        appId,
        cookie: true,
        xfbml: false,
        version: apiVersion,
        ...(configId ? { config_id: configId } : {}),
      });
      resolve(window.FB);
    };

    const script = document.createElement('script');
    script.id = 'facebook-jssdk';
    script.src = 'https://connect.facebook.net/en_US/sdk.js';
    script.async = true;
    script.defer = true;
    script.crossOrigin = 'anonymous';
    script.onerror = () => {
      sdkPromise = null;
      reject(new Error('sdk_load_failed'));
    };
    document.head.appendChild(script);
  });

  return sdkPromise;
}

/**
 * Launch the Meta Embedded Signup popup.
 *
 * Resolves with:
 *   {
 *     code,            // exchangeable authorization code (send to backend)
 *     sessionInfo,     // last WA_EMBEDDED_SIGNUP data payload (asset IDs)
 *     event,           // e.g. FINISH / FINISH_WHATSAPP_BUSINESS_APP_ONBOARDING
 *     events           // every WA_EMBEDDED_SIGNUP event seen during the flow
 *   }
 *
 * Rejects with an Error whose .code is one of:
 *   cancelled            user closed the popup / aborted the flow
 *   popup_blocked        FB.login did not produce a response (blocked popup)
 *   no_code              flow finished but Meta returned no code
 *   meta_flow_error      the Embedded Signup flow itself reported ERROR
 */
export function openMetaEmbeddedSignup({ appId, apiVersion, configId, featureType, sessionInfoVersion, onSessionEvent } = {}) {
  return loadFacebookSdk(appId, apiVersion, configId).then((FB) => new Promise((resolve, reject) => {
    const events = [];
    let lastSessionInfo = null;
    let finishEvent = null;
    let settled = false;

    const listener = (event) => {
      if (!META_ORIGINS.includes(event.origin)) {
        return;
      }

      let data = event.data;
      if (typeof data === 'string') {
        try {
          data = JSON.parse(data);
        } catch {
          return;
        }
      }

      if (!data || data.type !== 'WA_EMBEDDED_SIGNUP') {
        return;
      }

      events.push(data);
      if (data.data) {
        lastSessionInfo = data.data;
      }
      if (data.event === 'ERROR') {
        finishEvent = finishEvent || 'ERROR';
      } else if (typeof data.event === 'string' && data.event.startsWith('FINISH')) {
        finishEvent = data.event;
      } else if (data.event === 'CANCEL') {
        finishEvent = finishEvent || 'CANCEL';
      }

      if (typeof onSessionEvent === 'function') {
        try {
          onSessionEvent(data);
        } catch {
          // never let UI callbacks break the listener
        }
      }
    };

    window.addEventListener('message', listener);

    const cleanup = () => window.removeEventListener('message', listener);
    const fail = (code) => {
      if (settled) return;
      settled = true;
      cleanup();
      const error = new Error(code);
      error.code = code;
      error.events = events;
      reject(error);
    };

    FB.login((response) => {
      if (settled) return;

      const authResponse = response && response.authResponse;

      if (authResponse && authResponse.code) {
        settled = true;
        cleanup();
        resolve({
          code: authResponse.code,
          sessionInfo: lastSessionInfo,
          event: finishEvent,
          events,
        });
        return;
      }

      if (finishEvent === 'ERROR') {
        fail('meta_flow_error');
      } else {
        fail('cancelled');
      }
    }, {
      config_id: configId,
      response_type: 'code',
      override_default_response_type: true,
      extras: {
        setup: {},
        ...(featureType ? { featureType } : {}),
        ...(sessionInfoVersion ? { sessionInfoVersion } : {}),
      },
    });
  }));
}

export default { loadFacebookSdk, openMetaEmbeddedSignup };
