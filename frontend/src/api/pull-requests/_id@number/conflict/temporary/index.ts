import type {
  FixConflictTemporaryRequestItem,
  FixConflictTemporaryResponse,
} from '../../../../_pullRequest';

/**
 * POST /api/pull-requests/:id/conflict/temporary
 */
export type Methods = {
  post: {
    reqBody: {
      file: FixConflictTemporaryRequestItem;
    };
    resBody: FixConflictTemporaryResponse;
  };
};

