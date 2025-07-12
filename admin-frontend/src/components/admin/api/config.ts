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
      CREATE: '/api/admin/documents/create',
      UPDATE: '/api/admin/documents/update',
      DELETE: '/api/admin/documents/delete',
      GET: '/api/admin/documents',
      GET_DOCUMENT_DETAIL: '/api/admin/documents/detail',
      GET_DOCUMENT_CATEGORY_CONTENTS: '/api/admin/documents/category-contents',
    },
    CATEGORIES: {
      GET: '/api/admin/document-categories',
      CREATE: '/api/admin/document-categories/create',
      UPDATE: '/api/admin/document-categories/update',
      DELETE: '/api/admin/document-categories/delete',
    },
    USERS: {
      GET_ALL: '/api/admin/users',
      GET_BY_ID: '/api/admin/users',
    },
    USER_BRANCHES: {
      HAS_USER_CHANGES: '/api/admin/user-branches/has-changes',
      GET_DIFF: '/api/admin/user-branches/diff',
    },
    PULL_REQUEST_REVIEWERS: {
      GET: '/api/admin/pull-request-reviewers',
    },
    PULL_REQUESTS: {
      GET: '/api/admin/pull-requests',
      GET_DETAIL: '/api/admin/pull-requests',
      CREATE: '/api/admin/pull-requests/create',
      MERGE: '/api/admin/pull-requests',
      CONFLICT: '/api/admin/pull-requests',
      CLOSE: '/api/admin/pull-requests',
    },
    GIT: {
      CHECK_DIFF: '/api/admin/git/check-diff',
      GET_DIFF: '/api/admin/git/diff',
    },
  },
};
