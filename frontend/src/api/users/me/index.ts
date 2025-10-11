/**
 * ユーザー情報の型定義
 */
export interface User {
  id: number;
  email: string;
  nickname: string;
  organization_id: number;
  created_at: string;
  updated_at: string;
}

/**
 * 組織情報の型定義
 */
export interface Organization {
  id: number;
  name: string;
  created_at: string;
  updated_at: string;
}

/**
 * アクティブなユーザーブランチの型定義
 */
export interface ActiveUserBranch {
  id: number;
  user_id: number;
  branch_name: string;
  created_at: string;
  updated_at: string;
  pull_requests?: Array<{
    id: number;
    status: string;
    title: string;
    description?: string;
  }>;
}

/**
 * /api/users/meのレスポンス型定義
 */
export interface UserMeResponse {
  user: User;
  organization: Organization;
  activeUserBranch: ActiveUserBranch | null;
  nextAction: 'create_initial_commit' | 'create_subsequent_commit' | null;
}

export type Methods = {
  get: {
    resBody: UserMeResponse;
  };
};

