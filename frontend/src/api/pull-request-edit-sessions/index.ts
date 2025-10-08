import type { StartEditSessionResponse } from '../_pullRequest';

/**
 * POST /api/pull-request-edit-sessions (編集セッション開始)
 * PATCH /api/pull-request-edit-sessions (編集セッション終了)
 */
export type Methods = {
  post: {
    reqBody: {
      pull_request_id: string | number;
    };
    resBody: StartEditSessionResponse;
  };
  patch: {
    reqBody: {
      token: string;
      pull_request_id: string | number;
    };
    resBody: {
      success: boolean;
      message?: string;
    };
  };
};

