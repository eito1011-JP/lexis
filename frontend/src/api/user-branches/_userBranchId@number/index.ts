/**
 * ユーザーブランチ更新のリクエスト型
 */
export interface UpdateUserBranchRequest {
  is_active: boolean;
  user_branch_id: number;
}

/**
 * ユーザーブランチ更新のレスポンス型
 */
export interface UpdateUserBranchResponse {
  message: string;
}

/**
 * PUT /api/user-branches/:userBranchId
 */
export type Methods = {
  put: {
    reqBody: UpdateUserBranchRequest;
    resBody: UpdateUserBranchResponse;
  };
};

