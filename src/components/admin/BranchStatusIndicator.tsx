import React, { useEffect, useState } from 'react';
import { useSession } from '@site/src/contexts/SessionContext';
import { apiClient } from '@site/src/components/admin/api/client';

/**
 * ブランチの状態を表示するコンポーネント
 * ブランチの状態変更時に通知も表示する
 */
export default function BranchStatusIndicator(): JSX.Element | null {
  const { activeBranch, isAuthenticated, updateActiveBranch } = useSession();
  const [notification, setNotification] = useState<string | null>(null);
  const [isVisible, setIsVisible] = useState(false);

  // ブランチ情報を再取得する関数
  const refreshBranchStatus = async () => {
    if (!isAuthenticated) return;
    
    try {
      const response = await apiClient.get('/admin/git/current-branch');
      if (response.success && response.branch) {
        // 前のブランチと比較して変更があれば通知を表示
        if (
          activeBranch && 
          response.branch.branchName !== activeBranch.branchName
        ) {
          setNotification(`ブランチが ${response.branch.branchName} に切り替わりました`);
          setTimeout(() => setNotification(null), 5000); // 5秒後に通知を消す
        }
        
        updateActiveBranch(response.branch);
      }
    } catch (error) {
      console.error('ブランチ状態取得エラー:', error);
    }
  };

  // 初回マウント時にブランチ情報を取得
  useEffect(() => {
    if (isAuthenticated) {
      refreshBranchStatus();
    }
  }, [isAuthenticated]);

  // 通知の表示/非表示を制御
  useEffect(() => {
    if (notification) {
      setIsVisible(true);
      const timer = setTimeout(() => setIsVisible(false), 4500); // フェードアウト開始前にタイマーセット
      return () => clearTimeout(timer);
    }
  }, [notification]);

  if (!isAuthenticated || !activeBranch) {
    return null;
  }

  return (
    <>
      {/* 通知表示エリア */}
      {notification && (
        <div 
          className={`fixed top-4 right-4 bg-green-800 text-white px-4 py-2 rounded-md shadow-lg transition-opacity duration-500 ${
            isVisible ? 'opacity-100' : 'opacity-0'
          }`}
        >
          {notification}
        </div>
      )}
    </>
  );
} 