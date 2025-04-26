export const API_CONFIG = {
  // 環境に基づいたベースURLの設定
  BASE_URL: 'http://localhost:3001/api', // APIサーバーの絶対URLを指定

  TIMEOUT: 10000,

  ENDPOINTS: {
    SIGNUP: '/admin/signup',
    LOGIN: '/admin/login',
    SESSION: '/admin/session',
    LOGOUT: '/admin/logout',
    DOCUMENTS: {
      CREATE_FOLDER: '/admin/documents/create-folder',
      GET_FOLDERS: '/admin/documents/folders',
      GET_BY_ID: '/admin/documents', // IDはリクエスト時に追加
    },
    USERS: {
      GET_ALL: '/admin/users',
      GET_BY_ID: '/admin/users', // IDはリクエスト時に追加
    },
    GIT: {
      CHECK_DIFF: '/admin/documents/git/check-diff',
    },
  },
};
