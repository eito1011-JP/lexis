import { apiClient } from '../components/admin/api/client';
import { API_CONFIG } from '../components/admin/api/config';

// 差分アイテムの型定義
export interface DiffItem {
  id: number;
  type: 'document' | 'category';
}

// プルリクエスト作成リクエストの型定義
export interface CreatePullRequestRequest {
  user_branch_id: number;
  title: string;
  description?: string;
  diff_items: DiffItem[];
  reviewers?: string[];
}

// プルリクエスト作成レスポンスの型定義
export interface CreatePullRequestResponse {
  success: boolean;
  message: string;
  pr_url?: string;
  pr_number?: number;
  error?: string;
}

// レビュアー情報の型定義
export interface Reviewer {
  user_id: number;
  email: string;
  action_status: 'pending' | 'fix_requested' | 'approved';
}

// アクティビティログのアクター情報の型定義
export interface ActivityLogActor {
  id: number;
  name: string;
  email: string;
}

// アクティビティログのコメント情報の型定義
export interface ActivityLogComment {
  id: number;
  content: string;
  created_at: string;
}

// アクティビティログの修正リクエスト情報の型定義
export interface ActivityLogFixRequest {
  id: number;
  token: string;
  created_at: string;
}

// アクティビティログの型定義
export interface ActivityLog {
  id: number;
  pull_request_id: number;
  action: string;
  actor: ActivityLogActor | null;
  comment: ActivityLogComment | null;
  fix_request: ActivityLogFixRequest | null;
  old_pull_request_title: string | null;
  new_pull_request_title: string | null;
  created_at: string;
  fix_request_token: string | null;
}

// プルリクエスト詳細レスポンスの型定義
export interface PullRequestDetailResponse {
  document_versions: any[];
  document_categories: any[];
  original_document_versions?: any[];
  original_document_categories?: any[];
  diff_data: any[];
  title: string;
  description: string;
  status: 'opened' | 'merged' | 'closed' | 'conflict';
  author_name: string;
  author_email: string;
  reviewers: Reviewer[];
  created_at: string;
}

/**
 * プルリクエストを作成する
 */
export const createPullRequest = async (
  request: CreatePullRequestRequest
): Promise<CreatePullRequestResponse> => {
  try {
    const response = await apiClient.post(API_CONFIG.ENDPOINTS.PULL_REQUESTS.CREATE, request);
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

/**
 * プルリクエストの詳細を取得する
 */
export const fetchPullRequestDetail = async (
  id: string | number
): Promise<PullRequestDetailResponse> => {
  try {
    const response = await apiClient.get(`${API_CONFIG.ENDPOINTS.PULL_REQUESTS.GET_DETAIL}/${id}`);
    return response;
  } catch (error: any) {
    console.error('プルリクエスト詳細取得エラー:', error);
    throw error;
  }
};

/**
 * プルリクエストを承認する
 */
export const approvePullRequest = async (
  id: string | number
): Promise<{ success: boolean; error?: string }> => {
  try {
    await apiClient.patch(`${API_CONFIG.ENDPOINTS.PULL_REQUESTS.APPROVE}/${id}/approve`);
    return { success: true };
  } catch (error: any) {
    console.error('プルリクエスト承認エラー:', error);

    // エラーレスポンスの処理
    if (error.response?.data?.error) {
      return {
        success: false,
        error: error.response.data.error,
      };
    }

    return {
      success: false,
      error: 'プルリクエストの承認に失敗しました',
    };
  }
};

/**
 * プルリクエストのアクティビティログを取得する
 */
export const fetchActivityLog = async (pullRequestId: string): Promise<ActivityLog[]> => {
  try {
    const response = await apiClient.get(
      `${API_CONFIG.ENDPOINTS.PULL_REQUESTS.GET_DETAIL}/${pullRequestId}/activity-log-on-pull-request`
    );
    return response || [];
  } catch (error: any) {
    console.error('アクティビティログ取得エラー:', error);
    throw new Error('アクティビティログの取得に失敗しました');
  }
};
