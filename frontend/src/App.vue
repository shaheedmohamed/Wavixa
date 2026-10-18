<template>
  <div id="app">
    <GlobalLoader />
    <router-view />
  </div>
</template>

<script>
import { onMounted } from 'vue';
import api from '@/services/api';
import auth from '@/services/auth';
import GlobalLoader from '@/components/GlobalLoader.vue';

export default {
  name: 'App',
  components: { GlobalLoader },
  setup() {
    onMounted(() => {
      // Initialize API with stored token
      const token = auth.getToken();
      if (token) {
        api.setToken(token);
      }
    });

    return {};
  },
};
</script>

<style>
#app {
  width: 100%;
  min-height: 100vh;
}

a {
  color: inherit;
  text-decoration: none;
}
</style>
