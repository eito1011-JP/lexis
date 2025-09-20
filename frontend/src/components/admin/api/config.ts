export const API_CONFIG = {
  // 環境に基づいたベースURLの設定
  BASE_URL: '', // プロキシを使用するため空文字列に設定

  TIMEOUT: 10000,

  ENDPOINTS: {
    SIGNIN_WITH_EMAIL: '/api/auth/signin-with-email',
    LOGOUT: '/api/auth/logout',
    PRE_USERS_IDENTIFY: '/api/auth/pre-users',
    ORGANIZATIONS_CREATE: '/api/organizations',
    DOCUMENTS: {
      CREATE: '/api/documents/create',
      UPDATE: '/api/documents/update',
      DELETE: '/api/documents/delete',
      GET: '/api/documents',
      GET_DOCUMENT_DETAIL: '/api/documents/detail',
      GET_DOCUMENT_CATEGORY_CONTENTS: '/api/documents/category-contents',
    },
    DOCUMENT_VERSIONS: {
      CREATE: '/api/document_versions',
      GET_DETAIL: (id: number) => `/api/document_versions/${id}`,
    },
    CATEGORIES: {
      GET: '/api/document-categories',
      CREATE: '/api/document-categories',
      UPDATE: '/api/document-categories',
      DELETE: '/api/document-categories',
      GET_DETAIL: '/api/document-categories',
    },
    USERS: {
      GET_ALL: '/api/users',
    },
    USER_BRANCHES: {
      HAS_USER_CHANGES: '/api/user-branches/has-changes',
      GET_DIFF: '/api/user-branches/diff',
    },
    PULL_REQUEST_REVIEWERS: {
      GET: '/api/pull-request-reviewers',
      SEND_REVIEW_REQUEST_AGAIN: (reviewerId: number) =>
        `/api/pull-request-reviewers/${reviewerId}/resend`,
    },
    PULL_REQUESTS: {
      GET: '/api/pull-requests',
      GET_DETAIL: '/api/pull-requests',
      CREATE: '/api/pull-requests/create',
      FIX_REQUEST: '/api/pull-requests',
      MERGE: '/api/pull-requests',
      CONFLICT: '/api/pull-requests',
      CLOSE: '/api/pull-requests',
      APPROVE: '/api/pull-requests',
      UPDATE_TITLE: '/api/pull-requests',
    },
    GIT: {
      CHECK_DIFF: '/api/documents/git/check-diff',
      GET_DIFF: '/api/documents/git/diff',
    },
    FIX_REQUESTS: {
      GET_DIFF: '/api/fix-requests/:token',
    },
    ACTIVITY_LOGS: {
      CREATE: '/api/activity-logs',
    },
    PULL_REQUEST_EDIT_SESSIONS: {
      GET: '/api/pull-request-edit-sessions',
      START: '/api/pull-request-edit-sessions',
      FINISH: '/api/pull-request-edit-sessions',
    },
  },
};
