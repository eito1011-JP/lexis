export const API_CONFIG = {
  // 環境に基づいたベースURLの設定
  BASE_URL: '', // プロキシを使用するため空文字列に設定

  TIMEOUT: 10000,

  ENDPOINTS: {
    SIGNUP: '/api/admin/signup',
    LOGIN: '/api/admin/login',
    SESSION: '/api/auth/session',
    LOGOUT: '/api/admin/logout',
    DOCUMENTS: {
      CREATE_FOLDER: '/api/admin/documents/create-category',
      CREATE_DOCUMENT: '/api/admin/documents',
      UPDATE_DOCUMENT: '/api/admin/documents',
      GET_DOCUMENT: '/api/admin/documents',
      GET_DOCUMENT_BY_SLUG: '/api/admin/documents/slug',
      GET_DOCUMENT_CATEGORY_CONTENTS: '/api/admin/documents/category-contents',
    },
    USERS: {
      GET_ALL: '/api/admin/users',
      GET_BY_ID: '/api/admin/users',
    },
    GIT: {
      CHECK_DIFF: '/api/admin/documents/git/check-diff',
      CREATE_PR: '/api/admin/documents/git/create-pr',
    },
  },
};
