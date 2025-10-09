/**
 * ユーザーブランチ削除のレスポンス型
 */
export interface DestroyUserBranchResponse {}

/**
 * DELETE /api/user-branches/:userBranchId
 */
export type Methods = {
  delete: {
    resBody: DestroyUserBranchResponse;
  };
};

