export const API_CONFIG = {
  // 環境に基づいたベースURLの設定
  BASE_URL: '', // プロキシを使用するため空文字列に設定

  TIMEOUT: 10000,

  ENDPOINTS: {
    SIGNUP: '/api/admin/signup',
    LOGIN: '/api/admin/login',
    SESSION: '/api/admin/session',
    LOGOUT: '/api/admin/logout',
    DOCUMENTS: {
      CREATE_FOLDER: '/api/admin/documents/create-folder',
      GET_FOLDERS: '/api/admin/documents/folders',
      GET_BY_ID: '/api/admin/documents', // IDはリクエスト時に追加
    },
    USERS: {
      GET_ALL: '/api/admin/users',
      GET_BY_ID: '/api/admin/users', // IDはリクエスト時に追加
    },
    GIT: {
      CHECK_DIFF: '/api/admin/documents/git/check-diff',
    },
  },
};
