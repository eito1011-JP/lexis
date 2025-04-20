import React, { useEffect, useState } from 'react';
import { useSession } from '@site/src/contexts/SessionContext';
/**
 * ブランチの状態を表示するコンポーネント
 * ブランチの状態変更時に通知も表示する
 */
export default function BranchStatusIndicator(): JSX.Element | null {
  const { isAuthenticated } = useSession();
  const [notification, setNotification] = useState<string | null>(null);
  const [isVisible, setIsVisible] = useState(false);

  // 通知の表示/非表示を制御
  useEffect(() => {
    if (notification) {
      setIsVisible(true);
      const timer = setTimeout(() => setIsVisible(false), 4500); // フェードアウト開始前にタイマーセット
      return () => clearTimeout(timer);
    }
  }, [notification]);

  if (!isAuthenticated) {
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
