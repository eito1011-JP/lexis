/**
 * ユーザー変更チェックのレスポンス型
 */
export interface HasUserChangesResponse {
  has_user_changes: boolean;
  user_branch_id?: string;
}

/**
 * GET /api/user-branches/has-changes
 */
export type Methods = {
  get: {
    resBody: HasUserChangesResponse;
  };
};

