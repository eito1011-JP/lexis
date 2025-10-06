import useSWR from 'swr';
import { apiClient } from '@/components/admin/api/client';

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
}

/**
 * /api/users/meのレスポンス型定義
 */
export interface UserMeResponse {
  user: User;
  organization: Organization;
  activeUserBranch: ActiveUserBranch | null;
}

/**
 * APIフェッチャー関数
 */
const fetcher = async (url: string): Promise<UserMeResponse> => {
  const response = await apiClient.get(url);
  return response as UserMeResponse;
};

/**
 * どの画面からでも/api/users/meを呼び出してユーザー情報を取得できるカスタムフック
 * 
 * @returns SWRの戻り値（data, error, isLoading, mutate）
 * 
 * @example
 * ```tsx
 * const { data, error, isLoading } = useUserMe();
 * 
 * if (isLoading) return <div>読み込み中...</div>;
 * if (error) return <div>エラーが発生しました</div>;
 * if (!data) return null;
 * 
 * return <div>ようこそ、{data.user.name}さん</div>;
 * ```
 */
export const useUserMe = () => {
  const { data, error, isLoading, mutate } = useSWR<UserMeResponse>(
    '/api/users/me',
    fetcher,
    {
      // キャッシュ設定
      revalidateOnFocus: false, // フォーカス時の再検証を無効化
      revalidateOnReconnect: true, // 再接続時は再検証
      shouldRetryOnError: false, // エラー時のリトライを無効化（認証エラーの場合など）
      dedupingInterval: 2000, // 2秒間は同じリクエストを重複排除
    }
  );

  return {
    /** ユーザー情報、組織情報、アクティブブランチ情報 */
    data,
    /** ユーザー情報（短縮アクセス） */
    user: data?.user,
    /** 組織情報（短縮アクセス） */
    organization: data?.organization,
    /** アクティブなユーザーブランチ（短縮アクセス） */
    activeUserBranch: data?.activeUserBranch,
    /** エラー情報 */
    error,
    /** ローディング状態 */
    isLoading,
    /** データを手動で再検証する関数 */
    mutate,
  };
};
