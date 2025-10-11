import useAspidaSWR from '@aspida/swr';
import { client } from '@/api/client';
import type { UserMeResponse } from '@/api/users/me';

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
  const { data, error, isLoading, mutate } = useAspidaSWR(
    client.users.me,
    {
      // キャッシュ設定
      revalidateOnFocus: false, // フォーカス時の再検証を無効化
      revalidateOnReconnect: true, // 再接続時は再検証
      shouldRetryOnError: false, // エラー時のリトライを無効化（認証エラーの場合など）
      dedupingInterval: 2000, // 2秒間は同じリクエストを重複排除
    }
  );

  // dataをUserMeResponse型として型アサーション
  const typedData = data as UserMeResponse | undefined;

  return {
    /** ユーザー情報、組織情報、アクティブブランチ情報 */
    data: typedData,
    /** ユーザー情報（短縮アクセス） */
    user: typedData?.user,
    /** 組織情報（短縮アクセス） */
    organization: typedData?.organization,
    /** アクティブなユーザーブランチ（短縮アクセス） */
    activeUserBranch: typedData?.activeUserBranch,
    /** エラー情報 */
    error,
    /** ローディング状態 */
    isLoading,
    /** データを手動で再検証する関数 */
    mutate,
  };
};
