import { useState, useEffect, useRef } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { Settings } from '@/components/icon/common/Settings';
import { client } from '@/api/client';
import {
  createPullRequestSchema,
  type CreatePullRequestFormData,
  createCommitSchema,
  type CreateCommitFormData,
} from '@/schemas';
import { createPullRequest } from '@/api/pullRequestHelpers';
import { useToast } from '@/contexts/ToastContext';
import { useNavigate } from 'react-router-dom';
import { useUserMe } from '@/hooks/useUserMe';

type SubmitChangesFormProps = {
  organizationId: number;
  userBranchId: number;
};

/**
 * 差分提出フォームコンポーネント
 * 
 * ユーザーの編集状態（next_action）に応じて、以下のフォームを表示する：
 * - create_initial_commit: PR作成フォーム（タイトル、本文、レビュアー選択）
 * - create_subsequent_commit: コミット作成フォーム（編集内容メッセージ）
 * 
 * @param organizationId - 組織ID
 * @param userBranchId - ユーザーブランチID
 */
export const SubmitChangesForm = ({
  organizationId,
  userBranchId,
}: SubmitChangesFormProps) => {
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [selectedReviewers, setSelectedReviewers] = useState<number[]>([]);
  const [showReviewerModal, setShowReviewerModal] = useState(false);
  const [reviewerSearch, setReviewerSearch] = useState('');
  const reviewerModalRef = useRef<HTMLDivElement | null>(null);
  const [users, setUsers] = useState<any[]>([]);
  const [loadingUsers, setLoadingUsers] = useState(false);
  const { show } = useToast();
  const navigate = useNavigate();
  const { data: userData, mutate: mutateUserMe } = useUserMe();

  const nextAction = userData?.nextAction as 'create_initial_commit' | 'create_subsequent_commit' | null | undefined;

  /**
   * アクティブなPullRequestのIDを取得
   * Laravelからのレスポンスがスネークケース（pull_requests）で返ってくるため、
   * pull_requestsプロパティを使用
   */
  // @ts-ignore - pull_requestsはActiveUserBranch型に含まれています
  const pullRequestsArray = userData?.activeUserBranch?.pull_requests;
  
  const activePullRequest = pullRequestsArray?.find(
    (pr: any) => pr.status === 'opened'
  );
  const pullRequestId = activePullRequest?.id;

  // フォームの初期化（next_actionに応じて異なるスキーマを使用）
  const prForm = useForm<CreatePullRequestFormData>({
    resolver: zodResolver(createPullRequestSchema),
    mode: 'onBlur',
    defaultValues: {
      organization_id: organizationId,
      user_branch_id: userBranchId,
      title: '',
      description: '',
      reviewers: [],
    },
  });

  const commitForm = useForm<CreateCommitFormData>({
    resolver: zodResolver(createCommitSchema),
    mode: 'onBlur',
    defaultValues: {
      pull_request_id: 0,
      message: '',
    },
  });

  // pullRequestIdが変わったらフォームを更新
  useEffect(() => {
    if (pullRequestId) {
      commitForm.setValue('pull_request_id', pullRequestId);
    }
  }, [pullRequestId, commitForm]);

  const handleFetchUser = async (searchEmail?: string) => {
    setLoadingUsers(true);
    try {
      const query = searchEmail ? { email: searchEmail } : undefined;
      const response = await client.pull_request_reviewers.$get({ query });
      setUsers(response.users || []);
    } catch (error) {
      console.error('ユーザー取得エラー:', error);
      setUsers([]);
    } finally {
      setLoadingUsers(false);
    }
  };

  // レビュアーモーダルが表示された時にユーザー一覧を取得
  useEffect(() => {
    if (showReviewerModal) {
      handleFetchUser();
    }
  }, [showReviewerModal]);

  // レビュアー検索時の処理（300msのデバウンス）
  useEffect(() => {
    if (showReviewerModal && reviewerSearch) {
      const timeoutId = setTimeout(() => {
        handleFetchUser(reviewerSearch);
      }, 300);

      return () => clearTimeout(timeoutId);
    }
  }, [reviewerSearch, showReviewerModal]);

  // モーダル外クリック時の処理
  useEffect(() => {
    if (!showReviewerModal) return;
    const handleClickOutside = (event: MouseEvent) => {
      if (reviewerModalRef.current && !reviewerModalRef.current.contains(event.target as Node)) {
        setShowReviewerModal(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [showReviewerModal]);

  const onSubmitPR = async (data: CreatePullRequestFormData) => {
    setIsSubmitting(true);

    try {
      // レビュアーのメールアドレスを取得
      const reviewerEmails =
        selectedReviewers.length > 0
          ? users.filter(user => selectedReviewers.includes(user.id)).map(user => user.email)
          : undefined;

      // PRタイトル・説明をAPIに渡す
      await createPullRequest({
        organization_id: organizationId,
        user_branch_id: userBranchId,
        title: data.title,
        description: data.description || 'このPRはハンドブックの更新を含みます。',
        reviewers: reviewerEmails,
      });

      // ユーザー情報を更新してサイドコンテンツをリフレッシュ
      await mutateUserMe();

      show({ message: '差分の提出が完了しました', type: 'success' });
      navigate(`/change-suggestions/${pullRequestId}`);
    } catch (err: any) {
      console.error('差分提出エラー:', err);

      const status = err.response?.status;
      switch (status) {
        case 422:
          show({ message: '入力内容に不備があります。各項目を確認してください。', type: 'error' });
          break;
        case 401:
          show({ message: '認証エラーが発生しました。ログインし直してください。', type: 'error' });
          break;
        case 404:
          show({ message: 'リソースが見つかりません。', type: 'error' });
          break;
        default:
          show({ message: '差分の提出中にエラーが発生しました', type: 'error' });
          break;
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  const onSubmitCommit = async (data: CreateCommitFormData) => {
    setIsSubmitting(true);

    try {
      if (!pullRequestId) {
        show({ message: 'プルリクエストIDが見つかりません', type: 'error' });
        setIsSubmitting(false);
        return;
      }

      await client.commits.$post({
        body: {
          pull_request_id: pullRequestId,
          message: data.message,
        },
      });

      // ユーザー情報を更新してサイドコンテンツをリフレッシュ
      await mutateUserMe();

      show({ message: 'コミットの提出が完了しました', type: 'success' });
      navigate(`/change-suggestions/${pullRequestId}/diff`);
    } catch (err: any) {
      console.error('コミット提出エラー:', err);

      const status = err.response?.status;
      switch (status) {
        case 422:
          show({ message: '入力内容に不備があります。各項目を確認してください。', type: 'error' });
          break;
        case 401:
          show({ message: '認証エラーが発生しました。ログインし直してください。', type: 'error' });
          break;
        case 404:
          show({ message: 'プルリクエストが見つかりません。', type: 'error' });
          break;
        default:
          show({ message: 'コミットの提出中にエラーが発生しました', type: 'error' });
          break;
      }
    } finally {
      setIsSubmitting(false);
    }
  };

  // next_actionがない場合はnullを返す
  if (!nextAction) {
    return null;
  }

  return (
    <div className="mb-20 w-full rounded-lg relative">
      {nextAction === 'create_initial_commit' ? (
        /* PR作成フォーム */
        <div className="mb-6 relative w-full">
          <div className="mb-6 relative max-w-3xl w-full">
            <label className="block text-white text-base font-medium mb-3">タイトル</label>
            <input
              type="text"
              {...prForm.register('title')}
              className={`w-full px-4 py-3 pr-40 rounded-lg border ${prForm.formState.errors.title ? 'border-red-500' : 'border-gray-600'} text-white placeholder-gray-400 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500`}
              placeholder=""
              disabled={isSubmitting}
            />
            {prForm.formState.errors.title && (
              <div className="mt-2 text-red-400 text-sm">{prForm.formState.errors.title.message}</div>
            )}
          </div>

          <div className="absolute right-0 top-0 flex flex-col items-start mr-20">
            <div className="flex items-center gap-40 relative" ref={reviewerModalRef}>
              <span className="text-white text-base font-bold">レビュアー</span>
              <Settings
                className="w-5 h-5 text-gray-300 ml-2 cursor-pointer"
                onClick={() => setShowReviewerModal(v => !v)}
              />
              {showReviewerModal && (
                <div className="absolute left-0 top-full z-50 mt-2 w-full bg-[#181A1B] rounded-xl border border-gray-700 shadow-2xl">
                  <div className="flex flex-col">
                    <div className="px-5 pt-5 pb-2 border-b border-gray-700">
                      <div className="flex justify-between items-center mb-2">
                        <span className="text-white font-semibold text-base">
                          最大15人までリクエストできます
                        </span>
                      </div>
                      <input
                        type="text"
                        className="w-full px-3 py-2 rounded bg-[#222426] border border-gray-600 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500"
                        placeholder="Type or choose a user"
                        value={reviewerSearch}
                        onChange={e => setReviewerSearch(e.target.value)}
                        autoFocus
                      />
                    </div>
                    {/* Suggestionsセクション */}
                    <div className="px-5 pt-3">
                      <div className="text-xs text-gray-400 font-semibold mb-2">Suggestions</div>
                      {loadingUsers ? (
                        <div className="text-gray-500 text-sm py-2">読み込み中...</div>
                      ) : users.length === 0 ? (
                        <div className="text-gray-500 text-sm py-2">ユーザーが見つかりません</div>
                      ) : (
                        users
                          .filter(user =>
                            user.email.toLowerCase().includes(reviewerSearch.toLowerCase())
                          )
                          .map(user => (
                            <div
                              key={user.id}
                              className={`flex items-center gap-3 px-2 py-2 rounded cursor-pointer hover:bg-[#23272d] ${selectedReviewers.includes(user.id) ? 'bg-[#23272d]' : ''}`}
                              onClick={() =>
                                setSelectedReviewers(
                                  selectedReviewers.includes(user.id)
                                    ? selectedReviewers.filter(id => id !== user.id)
                                    : [...selectedReviewers, user.id]
                                )
                              }
                            >
                              <span className="text-2xl">👤</span>
                              <div className="flex-1 min-w-0">
                                <div className="text-white font-medium leading-tight">
                                  {user.email}
                                </div>
                                <div className="text-xs text-gray-400 truncate">
                                  {user.role || 'editor'}
                                </div>
                              </div>
                            </div>
                          ))
                      )}
                    </div>
                  </div>
                </div>
              )}
            </div>
            {selectedReviewers.length === 0 ? (
              <p className="text-white text-base font-medium mt-5 text-sm">レビュアーなし</p>
            ) : (
              <div className="mt-5">
                <div className="space-y-1">
                  {selectedReviewers.map(reviewerId => {
                    const user = users.find(u => u.id === reviewerId);
                    return user ? (
                      <div key={reviewerId} className="flex items-center gap-2 text-sm">
                        <span className="text-xl">👤</span>
                        <span className="text-gray-300">{user.email}</span>
                      </div>
                    ) : null;
                  })}
                </div>
              </div>
            )}
          </div>

          <div className="mb-8">
            <div className="mb-6 relative max-w-3xl w-full">
              <label className="block text-white text-base font-medium mb-3 max-w-3xl">
                本文
              </label>
              <textarea
                {...prForm.register('description')}
                className="w-full px-4 py-3 rounded-lg border border-gray-600 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 resize-none"
                placeholder=""
                rows={5}
                disabled={isSubmitting}
              />
            </div>
          </div>

          <div className="flex gap-4 justify-end max-w-3xl">
            <button
              className="px-6 py-2.5 bg-red-600 rounded-lg hover:bg-red-700 focus:outline-none text-white font-medium transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
              onClick={() => (window.location.href = '/documents')}
              disabled={isSubmitting}
            >
              戻る
            </button>
            <button
              type="button"
              className="px-6 py-2.5 bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none flex items-center text-white font-medium transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
              onClick={prForm.handleSubmit(onSubmitPR)}
              disabled={isSubmitting}
            >
              {isSubmitting ? (
                <>
                  <div className="animate-spin rounded-full h-4 w-4 border-t-2 border-b-2 border-white mr-2"></div>
                  <span>差分を提出中...</span>
                </>
              ) : (
                <span>差分を提出する</span>
              )}
            </button>
          </div>
        </div>
      ) : (
        /* Commit作成フォーム */
        <div className="mb-6 relative w-full">
          <div className="mb-6 relative max-w-3xl w-full">
            <label className="block text-white text-base font-medium mb-3">編集内容</label>
            <textarea
              {...commitForm.register('message')}
              className={`w-full px-4 py-3 rounded-lg border ${commitForm.formState.errors.message ? 'border-red-500' : 'border-gray-600'} text-white placeholder-gray-400 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 resize-none`}
              placeholder=""
              rows={5}
              disabled={isSubmitting}
            />
            {commitForm.formState.errors.message && (
              <div className="mt-2 text-red-400 text-sm">
                {commitForm.formState.errors.message.message}
              </div>
            )}
          </div>

          <div className="flex gap-4 justify-end max-w-3xl">
            <button
              className="px-6 py-2.5 bg-red-600 rounded-lg hover:bg-red-700 focus:outline-none text-white font-medium transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
              onClick={() => (window.location.href = '/documents')}
              disabled={isSubmitting}
            >
              戻る
            </button>
            <button
              type="button"
              className="px-6 py-2.5 bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none flex items-center text-white font-medium transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
              onClick={commitForm.handleSubmit(onSubmitCommit)}
              disabled={isSubmitting}
            >
              {isSubmitting ? (
                <>
                  <div className="animate-spin rounded-full h-4 w-4 border-t-2 border-b-2 border-white mr-2"></div>
                  <span>差分を更新中...</span>
                </>
              ) : (
                <span>差分を更新する</span>
              )}
            </button>
          </div>
        </div>
      )}
    </div>
  );
};

