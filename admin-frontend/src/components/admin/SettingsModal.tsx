import React, { useEffect, useRef, useState } from 'react';
import { apiClient } from './api/client';

interface SettingsModalProps {
  isOpen: boolean;
  onClose: () => void;
}

export default function SettingsModal({ isOpen, onClose }: SettingsModalProps): React.ReactElement | null {
  const [nickname, setNickname] = useState<string>('Eito');
  const [role] = useState<string>('オーナー');
  const [isLoggingOut, setIsLoggingOut] = useState<boolean>(false);
  const overlayRef = useRef<HTMLDivElement | null>(null);

  useEffect(() => {
    function onKeyDown(e: KeyboardEvent): void {
      if (e.key === 'Escape') onClose();
    }
    if (isOpen) {
      document.addEventListener('keydown', onKeyDown);
    }
    return () => document.removeEventListener('keydown', onKeyDown);
  }, [isOpen, onClose]);

  if (!isOpen) return null;

  const handleOverlayClick = (e: React.MouseEvent<HTMLDivElement>): void => {
    if (e.target === overlayRef.current) onClose();
  };

  const handleLogOut = async (): Promise<void> => {
    try {
      setIsLoggingOut(true);
      await apiClient.post('/api/auth/logout', {});
      
      // ログアウト成功後、ログインページにリダイレクト
      window.location.href = '/login';
    } catch (error) {
      console.error('ログアウトエラー:', error);
      alert('ログアウトに失敗しました。もう一度お試しください。');
    } finally {
      setIsLoggingOut(false);
    }
  };

  return (
    <div
      ref={overlayRef}
      onClick={handleOverlayClick}
      className="fixed inset-0 z-50 bg-black/70 flex items-start justify-center overflow-y-auto p-8"
    >
      <div className="w-full max-w-4xl text-[#FFFFFF]" role="dialog" aria-modal="true">
        <div className="flex items-start justify-between mb-6">
          <button
            type="button"
            onClick={onClose}
            className="text-gray-300 hover:text-white focus:outline-none"
            aria-label="Close"
          >
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
          </button>
        </div>

        <div className="bg-[#0A0A0A] border border-[#3E3E3E] rounded-xl">
          <div className="p-8">
            {/* アカウント */}
            <div className="mb-8">
              <div className="text-lg text-gray-300 mb-4">アカウント</div>

              <div className="flex items-center gap-4 mb-8">
                <div className="w-7 h-7 rounded-full overflow-hidden bg-gray-600 flex items-center justify-center">
                  <span className="text-xs">●</span>
                </div>
                <div className="bg-[#1B1B1B] rounded px-3 py-1 text-sm">Elto</div>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                <div className="flex items-center gap-6">
                  <div className="flex-1">
                    <label className="block text-sm text-gray-400 mb-2">ニックネーム</label>
                    <input
                      value={nickname}
                      onChange={e => setNickname(e.target.value)}
                      className="w-full bg-transparent border border-[#3E3E3E] rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                    />
                  </div>
                </div>
                <div>
                  <label className="block text-sm text-gray-400 mb-2">権限</label>
                  <select
                    value={role}
                    onChange={() => {}}
                    className="w-full bg-transparent border border-[#3E3E3E] rounded px-3 py-2 appearance-none"
                  >
                    <option className="bg-black">オーナー</option>
                  </select>
                </div>
              </div>
              <div className="mt-6">
                <button className="px-5 py-2 bg-indigo-600 rounded-md hover:bg-indigo-500 text-sm">保存</button>
              </div>
            </div>

            {/* 組織 */}
            <div className="mb-10">
              <div className="text-lg text-gray-300 mb-4">組織</div>
              <div className="flex items-center gap-3 text-xl">
                <svg className="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M3 7h18M3 12h18M3 17h18"></path>
                </svg>
                <div>株式会社Nexis</div>
              </div>
            </div>
          </div>

          {/* セキュリティ */}
          <div className="border-t border-[#3E3E3E] p-8">
            <div className="text-lg text-gray-300 mb-6">セキュリティ</div>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-6 items-end">
              <div className="md:col-span-2">
                <div className="text-sm text-gray-400 mb-1">Email</div>
                <div className="text-sm text-gray-200">eito.55855@gmail.com</div>
              </div>
              <div className="flex gap-3">
                <button className="px-4 py-2 border border-[#3E3E3E] rounded-md text-sm hover:bg-[#171717]">Emailを変更</button>
              </div>
            </div>

            <div className="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6 items-center">
              <div className="md:col-span-2">
                <div className="text-sm text-gray-400 mb-1">パスワード</div>
                <a className="text-sm text-gray-300 underline cursor-pointer">パスワードを忘れた方はこちら</a>
              </div>
              <div>
                <button className="px-4 py-2 border border-[#3E3E3E] rounded-md text-sm hover:bg-[#171717]">パスワードを変更</button>
              </div>
            </div>

            <div className="mt-6 flex justify-end">
              <button 
                onClick={handleLogOut}
                disabled={isLoggingOut}
                className="px-4 py-2 border border-[#3E3E3E] rounded-md text-sm hover:bg-[#171717] disabled:opacity-50 disabled:cursor-not-allowed"
              >
                {isLoggingOut ? 'ログアウト中...' : 'ログアウト'}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}


