import { apiClient } from '../components/admin/api/client';
import { API_CONFIG } from '../components/admin/api/config';

// 差分アイテムの型定義
export interface DiffItem {
  id: number;
  type: 'document' | 'category';
}

// プルリクエスト作成リクエストの型定義
export interface CreatePullRequestRequest {
  organization_id: number;
  user_branch_id: number;
  title: string;
  description?: string;
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

// アクティビティログのプルリクエスト編集セッション情報の型定義
export interface ActivityLogPullRequestEditSession {
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
  pull_request_edit_session: ActivityLogPullRequestEditSession | null;
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
    throw error;
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

/**
 * 編集終了を記録する
 */
export const createActivityLogOnPullRequest = async (pullRequestId: string): Promise<void> => {
  try {
    await apiClient.post(`${API_CONFIG.ENDPOINTS.ACTIVITY_LOGS.CREATE}`, {
      pull_request_id: pullRequestId,
      action: 'pull_request_edited',
    });
  } catch (error: any) {
    console.error('編集終了記録エラー:', error);
    throw new Error('編集終了の記録に失敗しました');
  }
};

// プルリクエスト編集セッション関連の型定義
export interface StartEditSessionResponse {
  token: string;
  session_id: number;
}

/**
 * プルリクエスト編集セッションを開始する
 */
export const startPullRequestEditSession = async (
  pullRequestId: string | number
): Promise<StartEditSessionResponse> => {
  try {
    const response = await apiClient.post(API_CONFIG.ENDPOINTS.PULL_REQUEST_EDIT_SESSIONS.START, {
      pull_request_id: pullRequestId,
    });
    return response;
  } catch (error: any) {
    console.error('プルリクエスト編集セッション開始エラー:', error);
    throw new Error('プルリクエスト編集セッションの開始に失敗しました');
  }
};

/**
 * プルリクエスト編集セッションを終了する
 */
export const finishPullRequestEditSession = async (
  token: string,
  pullRequestId: string | number
): Promise<void> => {
  try {
    await apiClient.patch(API_CONFIG.ENDPOINTS.PULL_REQUEST_EDIT_SESSIONS.FINISH, {
      token: token,
      pull_request_id: pullRequestId,
    });
  } catch (error: any) {
    console.error('プルリクエスト編集セッション終了エラー:', error);
    throw new Error('プルリクエスト編集セッションの終了に失敗しました');
  }
};

// コンフリクト差分の型定義
export interface ConflictFileDiff {
  filename: string;
  status: string;
  ancestorText: string | null;
  baseText: string | null;
  headText: string | null;
}

export interface ConflictDiffResponse {
  files: ConflictFileDiff[];
}

export interface FixConflictTemporaryRequestItem {
  filename: string;
  body: string; // 編集用本文（front-matter除去後）を想定
}

export interface FixConflictTemporaryResponse {
  is_conflict: boolean;
}

/**
 * コンフリクト差分を取得
 */
export const fetchConflictDiffs = async (
  pullRequestId: string | number
): Promise<ConflictDiffResponse> => {
  try {
    const response = await apiClient.get(
      `${API_CONFIG.ENDPOINTS.PULL_REQUESTS.CONFLICT}/${pullRequestId}/conflict/diff`
    );
    return response as ConflictDiffResponse;
  } catch (error: any) {
    console.error('コンフリクト差分取得エラー:', error);
    throw new Error(error?.response?.data?.error || 'コンフリクト差分の取得に失敗しました');
  }
};

/**
 * コンフリクト修正一時検証
 */
export const handleFixConflictTemporary = async (
  pullRequestId: string | number,
  item: FixConflictTemporaryRequestItem
): Promise<FixConflictTemporaryResponse> => {
  try {
    const response = await apiClient.post(
      `${API_CONFIG.ENDPOINTS.PULL_REQUESTS.CONFLICT}/${pullRequestId}/conflict/temporary`,
      { file: item }
    );

    return response as FixConflictTemporaryResponse;
  } catch (error: any) {
    if (error?.response?.data) {
      throw error.response.data;
    }
    throw { error: '一時検証に失敗しました' };
  }
};
