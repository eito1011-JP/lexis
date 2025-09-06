/**
 * API設定
 */
export const API_CONFIG = {
  ENDPOINTS: {
    // 認証関連
    LOGIN: '/auth/login',
    LOGOUT: '/auth/logout',
    SIGNUP: '/auth/signup',
    SESSION: '/auth/session',

    // ドキュメント関連
    DOCUMENTS: '/documents',
    DOCUMENT: '/documents/:id',

    // メディア関連
    MEDIA: '/media',
    MEDIA_UPLOAD: '/media/upload',

    // ユーザー関連
    USERS: '/users',
    USER: '/users/:id',

    // レビュー関連
    REVIEWS: '/reviews',
    REVIEW: '/reviews/:id',

    // 設定関連
    SETTINGS: '/settings',
  },
};
