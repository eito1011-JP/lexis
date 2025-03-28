export const API_ERRORS = {
    // 認証関連
    AUTH: {
      INVALID_METHOD: 'Method not allowed',
      MISSING_CREDENTIALS: 'メールアドレスとパスワードは必須です',
      INVALID_EMAIL_FORMAT: '有効なメールアドレスを入力してください',
      EMAIL_EXISTS: 'このメールアドレスは既に登録されています',
      INVALID_CREDENTIALS: 'メールアドレスまたはパスワードが正しくありません',
      UNAUTHORIZED: '認証が必要です',
      SESSION_EXPIRED: 'セッションの有効期限が切れました。再度ログインしてください',
    },
    
    // バリデーション関連
    VALIDATION: {
      PASSWORD_TOO_SHORT: 'パスワードは8文字以上である必要があります',
      PASSWORD_MISMATCH: 'パスワードと確認用パスワードが一致しません',
      REQUIRED_FIELD: '必須項目です',
      INVALID_FORMAT: '入力形式が正しくありません',
    },
    
    // サーバー関連
    SERVER: {
      INTERNAL_ERROR: 'サーバーエラーが発生しました',
      DATABASE_ERROR: 'データベースエラーが発生しました',
      RATE_LIMIT: 'リクエスト回数の上限に達しました。しばらく経ってから再度お試しください',
      SERVICE_UNAVAILABLE: 'サービスが一時的に利用できません',
    },
    
    // ユーザー管理関連
    USER: {
      NOT_FOUND: 'ユーザーが見つかりません',
      ACCOUNT_LOCKED: 'アカウントがロックされています',
    }
  };
  
  /**
   * 成功メッセージの定数
   */
  export const SUCCESS_MESSAGES = {
    AUTH: {
      SIGNUP_SUCCESS: 'ユーザー登録が完了しました',
      LOGIN_SUCCESS: 'ログインに成功しました',
      LOGOUT_SUCCESS: 'ログアウトしました',
      PASSWORD_RESET: 'パスワードがリセットされました',
    },
    USER: {
      PROFILE_UPDATED: 'プロフィールが更新されました',
      SETTINGS_SAVED: '設定が保存されました',
    }
  };
  
  /**
   * HTTPステータスコードの定数
   */
  export const HTTP_STATUS = {
    OK: 200,
    CREATED: 201,
    BAD_REQUEST: 400,
    UNAUTHORIZED: 401,
    FORBIDDEN: 403,
    NOT_FOUND: 404,
    METHOD_NOT_ALLOWED: 405,
    CONFLICT: 409,
    TOO_MANY_REQUESTS: 429,
    INTERNAL_SERVER_ERROR: 500,
    SERVICE_UNAVAILABLE: 503,
  };
