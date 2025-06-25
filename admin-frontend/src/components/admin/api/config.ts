export const API_CONFIG = {
  // 環境に基づいたベースURLの設定
  BASE_URL: '', // プロキシを使用するため空文字列に設定

  TIMEOUT: 10000,

  ENDPOINTS: {
    SIGNUP: '/api/auth/signup',
    LOGIN: '/api/auth/login',
    SESSION: '/api/auth/session',
    LOGOUT: '/api/auth/logout',
    DOCUMENTS: {
      CREATE_CATEGORY: '/api/admin/document-categories',
      UPDATE_CATEGORY: '/api/admin/document-categories',
      DELETE_CATEGORY: '/api/admiwn/document-categories',
      CREATE_DOCUMENT: '/api/admin/documents',
      UPDATE: '/api/admin/documents',
      DELETE: '/api/admin/documents',
      GET: '/api/admin/documents',
      GET_DOCUMENT_BY_CATEGORY_PATH: '/api/admin/documents',
      GET_DOCUMENT_CATEGORY_CONTENTS: '/api/admin/documents/category-contents',
      GET_CATEGORIES: '/api/admin/document-categories',
    },
    CATEGORIES: {
      CREATE: '/api/admin/document-categories',
      UPDATE: '/api/admin/document-categories',
      DELETE: '/api/admin/document-categories',
      GET_BY_PATH: '/api/admin/document-categories',
    },
    USERS: {
      GET_ALL: '/api/admin/users',
      GET_BY_ID: '/api/admin/users',
    },
    GIT: {
      CHECK_DIFF: '/api/admin/documents/git/check-diff',
      CREATE_PR: '/api/admin/documents/git/create-pr',
      GET_DIFF: '/api/admin/documents/git/diff',
    },
  },
};
