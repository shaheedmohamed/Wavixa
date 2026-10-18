<template>
  <div>
    <div v-if="loading" class="flex justify-center py-4">
      <div class="w-6 h-6 border-4 border-cyan-500/30 border-t-cyan-500 rounded-full animate-spin"></div>
    </div>
    <div v-else-if="error" class="bg-red-500/20 border border-red-500/50 rounded-lg p-3 text-center">
      <p class="text-red-400 text-sm">{{ error }}</p>
    </div>
    <div v-else ref="paypalContainer" class="paypal-button-container"></div>
  </div>
</template>

<script>
import { ref, onMounted, watch } from 'vue';
import api from '@/services/api';

export default {
  name: 'PayPalButton',
  props: {
    plan: {
      type: Object,
      required: true,
    },
  },
  emits: ['success', 'error'],
  setup(props, { emit }) {
    const loading = ref(true);
    const error = ref('');
    const paypalContainer = ref(null);
    const paypalConfig = ref(null);
    let paypalButton = null;

    const loadPayPalConfig = async () => {
      try {
        const response = await api.get('/paypal/config');
        if (response.success) {
          paypalConfig.value = response.data;
        } else {
          error.value = response.message || 'Failed to load PayPal config';
        }
      } catch (err) {
        error.value = err.data?.message || 'Failed to load PayPal config';
      }
    };

    const loadPayPalScript = () => {
      return new Promise((resolve, reject) => {
        if (window.paypal) {
          resolve(window.paypal);
          return;
        }

        if (!paypalConfig.value || !paypalConfig.value.client_id) {
          reject(new Error('PayPal config not available'));
          return;
        }

        const baseUrl = paypalConfig.value.mode === 'live'
          ? 'https://www.paypal.com/sdk/js'
          : 'https://www.sandbox.paypal.com/sdk/js';

        const params = new URLSearchParams({
          'client-id': paypalConfig.value.client_id,
          currency: paypalConfig.value.currency || 'USD',
          intent: 'capture',
        });

        const script = document.createElement('script');
        script.src = `${baseUrl}?${params.toString()}`;
        script.async = true;
        script.onload = () => resolve(window.paypal);
        script.onerror = () => reject(new Error('Failed to load PayPal SDK'));
        document.head.appendChild(script);
      });
    };

    const renderButton = async () => {
      try {
        loading.value = true;
        error.value = '';

        await loadPayPalConfig();
        const paypal = await loadPayPalScript();

        if (paypalButton) {
          paypalButton.close();
        }

        loading.value = false;
        await new Promise(resolve => setTimeout(resolve, 0));

        paypalButton = paypal.Buttons({
          style: {
            layout: 'vertical',
            color: 'gold',
            shape: 'rect',
            label: 'pay',
          },
          createOrder: async () => {
            try {
              const response = await api.post('/paypal/create-order', {
                plan_id: props.plan.id,
              });

              if (response.success) {
                return response.data.order_id;
              }

              throw new Error(response.message || 'Failed to create order');
            } catch (err) {
              error.value = err.data?.message || err.message || 'Failed to create order';
              throw err;
            }
          },
          onApprove: async (data) => {
            try {
              const response = await api.post('/paypal/capture-order', {
                order_id: data.orderID,
                plan_id: props.plan.id,
              });

              if (response.success) {
                emit('success', response.data);
              } else {
                throw new Error(response.message || 'Payment failed');
              }
            } catch (err) {
              error.value = err.data?.message || err.message || 'Payment failed';
              emit('error', err);
              throw err;
            }
          },
          onError: (err) => {
            error.value = typeof err === 'string' ? err : 'PayPal button error';
            emit('error', err);
          },
          onCancel: () => {
            error.value = 'Payment cancelled';
          },
        });

        await paypalButton.render(paypalContainer.value);
      } catch (err) {
        loading.value = false;
        error.value = err.message || 'Failed to load PayPal';
        emit('error', err);
      }
    };

    watch(() => props.plan, () => {
      renderButton();
    }, { deep: true });

    onMounted(() => {
      renderButton();
    });

    return {
      loading,
      error,
      paypalContainer,
    };
  },
};
</script>

<style scoped>
.paypal-button-container {
  min-height: 45px;
}
</style>
