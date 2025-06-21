import type { Plugin } from '@docusaurus/types';

export default function apiProxyPlugin(): Plugin<void> {
  return {
    name: 'api-proxy',
    configureWebpack(config, isServer, utils) {
      if (!isServer) {
        return {
          devServer: {
            proxy: {
              '/api': {
                target: 'http://localhost:8000',
                changeOrigin: true,
                secure: false,
              },
            },
          },
        };
      }
      return {};
    },
  };
} 