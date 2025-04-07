export const API_CONFIG = {
  // 環境に基づいたベースURLの設定
  BASE_URL:
    process.env.NODE_ENV === 'production'
      ? '/api' // 本番環境: 同じドメイン内の/apiパス
      : 'http://localhost:3001/api', // 開発環境: 別ポートの3001

  TIMEOUT: 10000,

  ENDPOINTS: {
    SIGNUP: '/admin/signup',
    LOGIN: '/admin/login',
  },
};
