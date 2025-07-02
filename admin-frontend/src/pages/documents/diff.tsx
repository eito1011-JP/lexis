import AdminLayout from '@/components/admin/layout';
import { useState, useEffect } from 'react';
import type { JSX } from 'react';
import { useSessionCheck } from '@/hooks/useSessionCheck';
import { apiClient } from '@/components/admin/api/client';
import { API_CONFIG } from '@/components/admin/api/config';
import { Folder } from '@/components/icon/common/Folder';
import React from 'react';
import { markdownToHtml } from '@/utils/markdownToHtml';
import { DocumentDetailed } from '@/components/icon/common/DocumentDetailed';
import { Settings } from '@/components/icon/common/Settings';
import { createPullRequest, type DiffItem as ApiDiffItem } from '@/api/pullRequest';

// 差分データの型定義
type DiffItem = {
  id: number;
  slug: string;
  sidebar_label: string;
  description?: string;
  title?: string;
  content?: string;
  position?: number;
  file_order?: number;
  parent_id?: number;
  category_id?: number;
  status: string;
  user_branch_id: number;
  created_at: string;
  updated_at: string;
};

type DiffResponse = {
  document_versions: DiffItem[];
  document_categories: DiffItem[];
  original_document_versions?: DiffItem[];
  original_document_categories?: DiffItem[];
};

// 差分表示コンポーネント
const DiffField = ({ label, value }: { label: string; value?: string }) => {
  return (
    <div className="mb-4">
      <label className="block text-sm font-medium text-gray-300 mb-2">{label}</label>
      <div className="bg-gray-800 rounded-md p-3 text-sm text-gray-300">{value || '-'}</div>
    </div>
  );
};

// 差分表示（original: 赤, current: 緑）
const DiffValue = ({
  label,
  original,
  current,
  isMarkdown = false,
}: {
  label: string;
  original?: string;
  current?: string;
  isMarkdown?: boolean;
}) => (
  <div className="mb-4">
    <label className="block text-sm font-medium text-gray-300 mb-2">{label}</label>
    {original !== undefined && (
      <div className="bg-red-900/50 border border-red-800 rounded-md p-3 text-sm text-red-200 mb-1">
        {isMarkdown ? (
          <div dangerouslySetInnerHTML={{ __html: markdownToHtml(original || '-') }} />
        ) : (
          original || '-'
        )}
      </div>
    )}
    {current !== undefined && (
      <div className="bg-green-900/50 border border-green-800 rounded-md p-3 text-sm text-green-200">
        {isMarkdown ? (
          <div dangerouslySetInnerHTML={{ __html: markdownToHtml(current || '-') }} />
        ) : (
          current || '-'
        )}
      </div>
    )}
  </div>
);

// 階層パンくずリストコンポーネント
const SlugBreadcrumb = ({ slug }: { slug: string }) => {
  const slugParts = slug.split('/').filter(part => part.length > 0);
  let currentPath = '';

  return (
    <div className="mb-3">
      <div className="flex items-center text-sm text-gray-400 mb-2">
        {slugParts.map((part, index) => {
          // パスを構築（現在までの部分）
          currentPath += (index === 0 ? '' : '/') + part;

          return (
            <React.Fragment key={index}>
              {index > 0 && (
                <span className="mx-2">
                  <svg
                    className="w-3 h-3"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                    xmlns="http://www.w3.org/2000/svg"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth="2"
                      d="M9 5l7 7-7 7"
                    ></path>
                  </svg>
                </span>
              )}
              {index === slugParts.length - 1 ? (
                <span className="text-white">{part}</span>
              ) : (
                <span className="text-gray-400">{part}</span>
              )}
            </React.Fragment>
          );
        })}
      </div>
      <div className="border-b border-gray-700"></div>
    </div>
  );
};

/**
 * 差分確認画面コンポーネント
 */
export default function DiffPage(): JSX.Element {
  const { isLoading } = useSessionCheck('/login', false);

  const [diffData, setDiffData] = useState<DiffResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [submitSuccess, setSubmitSuccess] = useState<string | null>(null);
  const [prTitle, setPrTitle] = useState('');
  const [prDescription, setPrDescription] = useState('');
  // レビューアー選択用の仮データと状態
  const reviewersList = [
    { id: 1, name: 'ユーザーA' },
    { id: 2, name: 'ユーザーB' },
    { id: 3, name: 'ユーザーC' },
  ];
  const [selectedReviewers, setSelectedReviewers] = useState<number[]>([]);

  const handleReviewerChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    const options = Array.from(e.target.selectedOptions);
    setSelectedReviewers(options.map(opt => Number(opt.value)));
  };

  useEffect(() => {
    const fetchDiff = async () => {
      try {
        // URLパラメータからuser_branch_idを取得
        const urlParams = new URLSearchParams(window.location.search);
        const userBranchId = urlParams.get('user_branch_id');

        if (!userBranchId) {
          setError('user_branch_idパラメータが必要です');
          setLoading(false);
          return;
        }

        const response = await apiClient.get(
          `${API_CONFIG.ENDPOINTS.USER_BRANCHES.GET_DIFF}?user_branch_id=${userBranchId}`
        );

        setDiffData(response);
      } catch (err) {
        console.error('差分取得エラー:', err);
        setError('差分データの取得に失敗しました');
      } finally {
        setLoading(false);
      }
    };

    fetchDiff();
  }, []);

  // PR作成のハンドラー
  const handleSubmitPR = async () => {
    setIsSubmitting(true);
    setSubmitError(null);
    setSubmitSuccess(null);

    try {
      // URLパラメータからuser_branch_idを取得
      const urlParams = new URLSearchParams(window.location.search);
      const userBranchId = urlParams.get('user_branch_id');

      if (!userBranchId) {
        setSubmitError('user_branch_idパラメータが必要です');
        return;
      }

      // diffアイテムを構築
      const diffItems: ApiDiffItem[] = [];

      // ドキュメントバージョンを追加
      if (diffData?.document_versions) {
        diffData.document_versions.forEach(doc => {
          diffItems.push({
            id: doc.id,
            type: 'document',
          });
        });
      }

      // カテゴリを追加
      if (diffData?.document_categories) {
        diffData.document_categories.forEach(cat => {
          diffItems.push({
            id: cat.id,
            type: 'category',
          });
        });
      }

      // PRタイトル・説明をAPIに渡す
      const response = await createPullRequest({
        user_branch_id: parseInt(userBranchId),
        title: prTitle || '更新内容の提出',
        body: prDescription || 'このPRはハンドブックの更新を含みます。',
        diff_items: diffItems,
      });

      if (response.success) {
        const successMessage = response.pr_url
          ? `差分の提出が完了しました。PR: ${response.pr_url}`
          : '差分の提出が完了しました';
        setSubmitSuccess(successMessage);
        // 3秒後にドキュメント一覧に戻る
        setTimeout(() => {
          window.location.href = '/admin/documents';
        }, 3000);
      } else {
        setSubmitError(response.message || '差分の提出に失敗しました');
      }
    } catch (err) {
      console.error('差分提出エラー:', err);
      setSubmitError('差分の提出中にエラーが発生しました');
    } finally {
      setIsSubmitting(false);
    }
  };

  // セッション確認中はローディング表示
  if (isLoading) {
    return (
      <AdminLayout title="読み込み中...">
        <div className="flex flex-col items-center justify-center h-full">
          <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-white mb-4"></div>
        </div>
      </AdminLayout>
    );
  }

  // データ読み込み中
  if (loading) {
    return (
      <AdminLayout title="差分確認">
        <div className="flex flex-col items-center justify-center h-full">
          <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-white mb-4"></div>
          <p className="text-gray-400">差分データを読み込み中...</p>
        </div>
      </AdminLayout>
    );
  }

  // エラー表示
  if (error) {
    return (
      <AdminLayout title="差分確認">
        <div className="flex flex-col items-center justify-center h-full">
          <div className="mb-4 p-3 bg-red-900/50 border border-red-800 rounded-md text-red-200">
            <div className="flex items-center">
              <svg
                className="w-5 h-5 mr-2 text-red-300"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth="2"
                  d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                />
              </svg>
              <span>{error}</span>
            </div>
          </div>
          <button
            className="px-4 py-2 bg-[#3832A5] rounded-md hover:bg-[#28227A] focus:outline-none"
            onClick={() => (window.location.href = '/admin/documents')}
          >
            ドキュメント一覧に戻る
          </button>
        </div>
      </AdminLayout>
    );
  }

  // データが空の場合
  if (
    !diffData ||
    (diffData.document_categories.length === 0 && diffData.document_versions.length === 0)
  ) {
    return (
      <AdminLayout title="差分確認">
        <div className="flex flex-col items-center justify-center h-full">
          <p className="text-gray-400 mb-4">変更された内容はありません</p>
          <button
            className="px-4 py-2 bg-[#3832A5] rounded-md hover:bg-[#28227A] focus:outline-none"
            onClick={() => (window.location.href = '/admin/documents')}
          >
            ドキュメント一覧に戻る
          </button>
        </div>
      </AdminLayout>
    );
  }

  // original/currentをslugでマッピング
  const mapBySlug = (arr: DiffItem[]) => Object.fromEntries(arr.map(item => [item.slug, item]));

  const originalDocs = mapBySlug(diffData.original_document_versions || []);
  const currentDocs = mapBySlug(diffData.document_versions || []);
  const allDocSlugs = Array.from(
    new Set([
      ...(diffData.original_document_versions || []).map(d => d.slug),
      ...(diffData.document_versions || []).map(d => d.slug),
    ])
  );

  const originalCats = mapBySlug(diffData.original_document_categories || []);
  const currentCats = mapBySlug(diffData.document_categories || []);
  const allCatSlugs = Array.from(
    new Set([
      ...(diffData.original_document_categories || []).map(c => c.slug),
      ...(diffData.document_categories || []).map(c => c.slug),
    ])
  );

  return (
    <AdminLayout title="差分確認">
      <div className="flex flex-col h-full">
        {/* 成功メッセージ */}
        {submitSuccess && (
          <div className="mb-4 p-3 bg-green-900/50 border border-green-800 rounded-md text-green-200">
            <div className="flex items-center">
              <svg
                className="w-5 h-5 mr-2 text-green-300"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth="2"
                  d="M5 13l4 4L19 7"
                />
              </svg>
              <span>{submitSuccess}</span>
            </div>
            <p className="text-sm mt-2">3秒後にドキュメント一覧に戻ります...</p>
            {submitSuccess.includes('PR:') && (
              <p className="text-sm mt-1">
                <a
                  href={submitSuccess.split('PR: ')[1]}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="text-blue-300 hover:text-blue-200 underline"
                >
                  PRを開く
                </a>
              </p>
            )}
          </div>
        )}

        {/* エラーメッセージ */}
        {submitError && (
          <div className="mb-4 p-3 bg-red-900/50 border border-red-800 rounded-md text-red-200">
            <div className="flex items-center">
              <svg
                className="w-5 h-5 mr-2 text-red-300"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth="2"
                  d="M6 18L18 6M6 6l12 12"
                />
              </svg>
              <span>{submitError}</span>
            </div>
          </div>
        )}

        {/* PR作成セクション（画像のようなデザイン） */}
        <div className="mb-20 w-full rounded-lg relative">
          {/* タイトル入力欄とレビュアーを重ねて配置 */}
          <div className="mb-6 relative w-full">
            <div className="mb-6 relative max-w-3xl w-full">
              <label className="block text-white text-base font-medium mb-3">タイトル</label>
              <input
                type="text"
                className="w-full px-4 py-3 pr-40 rounded-lg border border-gray-600 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                placeholder=""
                value={prTitle}
                onChange={e => setPrTitle(e.target.value)}
                disabled={isSubmitting}
              />
            </div>

            <div className="absolute right-0 top-0 flex flex-col items-start mr-20">
              <div className="flex items-center gap-40">
                <span className="text-white text-base font-bold">レビュアー</span>
                <Settings className="w-5 h-5 text-gray-300 ml-2" />
              </div>
              <p className="text-white text-base font-medium mt-5 text-sm">レビュアーなし</p>
            </div>
            <div className="mb-8">
              <div className="mb-6 relative max-w-3xl w-full">
                <label className="block text-white text-base font-medium mb-3 max-w-3xl">
                  本文
                </label>
                <textarea
                  className="w-full px-4 py-3 rounded-lg border border-gray-600 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 resize-none"
                  placeholder=""
                  rows={5}
                  value={prDescription}
                  onChange={e => setPrDescription(e.target.value)}
                  disabled={isSubmitting}
                />
              </div>
            </div>

            <div className="flex gap-4 justify-end max-w-3xl">
              <button
                className="px-6 py-2.5 bg-red-600 rounded-lg hover:bg-red-700 focus:outline-none text-white font-medium transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                onClick={() => (window.location.href = '/admin/documents')}
                disabled={isSubmitting}
              >
                戻る
              </button>
              <button
                className="px-6 py-2.5 bg-blue-600 rounded-lg hover:bg-blue-700 focus:outline-none flex items-center text-white font-medium transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                onClick={handleSubmitPR}
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
        </div>

        {/* 変更されたカテゴリの詳細 */}
        {allCatSlugs.length > 0 && (
          <div className="mb-10">
            <h2 className="text-xl font-bold mb-4 flex items-center">
              <Folder className="w-5 h-5 mr-2" />
              カテゴリの変更 × {allCatSlugs.length}
            </h2>
            <div className="space-y-4 mr-20">
              {allCatSlugs.map(slug => {
                const original = originalCats[slug];
                const current = currentCats[slug];
                return (
                  <div key={slug} className="bg-gray-900 rounded-lg border border-gray-800 p-6">
                    <SlugBreadcrumb slug={slug} />
                    <DiffValue label="Slug" original={original?.slug} current={current?.slug} />
                    <DiffValue
                      label="カテゴリ名"
                      original={original?.sidebar_label}
                      current={current?.sidebar_label}
                    />
                    <DiffValue
                      label="表示順"
                      original={original?.position?.toString()}
                      current={current?.position?.toString()}
                    />
                    <DiffValue
                      label="説明"
                      original={original?.description}
                      current={current?.description}
                    />
                  </div>
                );
              })}
            </div>
          </div>
        )}

        {/* ドキュメントの変更数表示 */}
        <h2 className="text-xl font-bold mb-4 flex items-center">
          <DocumentDetailed className="w-6 h-6 mr-2" />
          ドキュメントの変更 × {allDocSlugs.length}
        </h2>

        {/* 変更されたドキュメントの詳細 */}
        {allDocSlugs.length > 0 && (
          <div className="mb-8 mr-20">
            <div className="space-y-6">
              {allDocSlugs.map(slug => {
                const original = originalDocs[slug];
                const current = currentDocs[slug];
                return (
                  <div key={slug} className="bg-gray-900 rounded-lg border border-gray-800 p-6">
                    <SlugBreadcrumb slug={slug} />
                    <DiffValue label="Slug" original={original?.slug} current={current?.slug} />
                    <DiffValue
                      label="タイトル"
                      original={original?.sidebar_label}
                      current={current?.sidebar_label}
                    />
                    <DiffValue
                      label="公開設定"
                      original={
                        original
                          ? original.status === 'published'
                            ? '公開する'
                            : '公開しない'
                          : undefined
                      }
                      current={
                        current
                          ? current.status === 'published'
                            ? '公開する'
                            : '公開しない'
                          : undefined
                      }
                    />
                    <DiffValue
                      label="本文"
                      original={original?.content}
                      current={current?.content}
                      isMarkdown
                    />
                  </div>
                );
              })}
            </div>
          </div>
        )}
      </div>
    </AdminLayout>
  );
}
