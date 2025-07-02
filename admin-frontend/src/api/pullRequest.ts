import { apiClient } from './client';

// 差分アイテムの型定義
export interface DiffItem {
  id: number;
  type: 'document' | 'category';
}

// プルリクエスト作成リクエストの型定義
export interface CreatePullRequestRequest {
  user_branch_id: number;
  title: string;
  body?: string;
  diff_items: DiffItem[];
}

// プルリクエスト作成レスポンスの型定義
export interface CreatePullRequestResponse {
  success: boolean;
  message: string;
  pr_url?: string;
  pr_number?: number;
  error?: string;
}

/**
 * プルリクエストを作成する
 */
export const createPullRequest = async (
  request: CreatePullRequestRequest
): Promise<CreatePullRequestResponse> => {
  try {
    const response = await apiClient.post('/admin/user-branches/create-pull-request', request);
    return response;
  } catch (error: any) {
    console.error('プルリクエスト作成エラー:', error);
    
    // エラーレスポンスの処理
    if (error.response?.data?.error) {
      return {
        success: false,
        message: error.response.data.error,
        error: error.response.data.error,
      };
    }
    
    return {
      success: false,
      message: 'プルリクエストの作成に失敗しました',
      error: error.message || 'Unknown error',
    };
  }
}; 