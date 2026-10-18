import auth from './auth';

export function track(event, data = {}, eventId = null) {
  if (typeof window === 'undefined' || !window.fbq) return;

  const options = eventId ? { eventID: eventId } : undefined;
  window.fbq('track', event, data, options);
}

export function trackCustom(event, data = {}, eventId = null) {
  if (typeof window === 'undefined' || !window.fbq) return;

  const options = eventId ? { eventID: eventId } : undefined;
  window.fbq('trackCustom', event, data, options);
}

export function userId() {
  const user = auth.getUser();
  return user ? user.id : null;
}
