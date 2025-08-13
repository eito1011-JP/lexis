import React, { useEffect, useMemo, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import AdminLayout from '@/components/admin/layout';
import { useSessionCheck } from '@/hooks/useSessionCheck';
import { DiffDisplay } from '@/components/diff/DiffDisplay';
import { fetchConflictDiffs, type ConflictFileDiff } from '@/api/pullRequest';

const ConflictResolutionPage: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { isLoading } = useSessionCheck('/login', false);

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [files, setFiles] = useState<ConflictFileDiff[]>([]);

  useEffect(() => {
    let mounted = true;
    const run = async () => {
      if (!id) return;
      setLoading(true);
      setError(null);
      try {
        const res = await fetchConflictDiffs(id);
        if (!mounted) return;
        setFiles(res.files || []);
      } catch (e: any) {
        if (!mounted) return;
        setError(e.message || 'コンフリクト差分の取得に失敗しました');
      } finally {
        if (mounted) setLoading(false);
      }
    };
    run();
    return () => {
      mounted = false;
    };
  }, [id]);

  const leftList = useMemo(() => files.map(f => f.filename), [files]);

  if (isLoading) {
    return (
      <AdminLayout title="読み込み中..." sidebar={false}>
        <div className="flex items-center justify-center h-full">
          <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-white"></div>
        </div>
      </AdminLayout>
    );
  }

  return (
    <AdminLayout title="変更の競合詳細" sidebar={false}>
      <div className="flex gap-6">
        {/* 左: 変更のぶつかり一覧 */}
        <div className="w-64">
          <div className="text-white font-semibold mb-2">変更のぶつかり一覧</div>
          <div className="space-y-2">
            {leftList.length === 0 && (
              <div className="text-gray-400 text-sm">対象ファイルがありません</div>
            )}
            {leftList.map(name => (
              <div
                key={name}
                className="text-xs px-3 py-2 rounded border border-gray-700 text-white bg-[#171717]"
              >
                {name}
              </div>
            ))}
          </div>
        </div>

        {/* 右: ファイルごとの3カラム比較 */}
        <div className="flex-1">
          <div className="text-2xl font-bold text-white mb-4">初めてのPR提出</div>

          {error && (
            <div className="mb-4 p-3 bg-red-900/50 border border-red-800 rounded-md text-red-200">
              {error}
            </div>
          )}

          {loading ? (
            <div className="flex items-center justify-center h-40">
              <div className="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-white"></div>
            </div>
          ) : (
            <div className="space-y-10">
              {files.map((file, idx) => (
                <div key={file.filename + idx} className="border border-gray-700 rounded-lg p-5">
                  <div className="text-gray-400 text-xs mb-2">Slug</div>
                  <div className="grid grid-cols-2 gap-4 mb-4">
                    <input
                      className="px-3 py-2 rounded border border-gray-700 bg-[#111113] text-white"
                      value={file.filename}
                      readOnly
                    />
                    <input
                      className="px-3 py-2 rounded border border-gray-700 bg-[#111113] text-white"
                      value={file.filename}
                      readOnly
                    />
                  </div>

                  <div className="grid grid-cols-2 gap-4 mb-4">
                    <div>
                      <div className="text-gray-400 text-xs mb-2">タイトル</div>
                      <input
                        className="w-full px-3 py-2 rounded border border-gray-700 bg-[#111113] text-white"
                        readOnly
                        value="生存性について"
                      />
                    </div>
                    <div>
                      <div className="text-gray-400 text-xs mb-2">タイトル</div>
                      <input
                        className="w-full px-3 py-2 rounded border border-gray-700 bg-[#111113] text-white"
                        readOnly
                        value="生存性について"
                      />
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-4 mb-4">
                    <div>
                      <div className="text-gray-400 text-xs mb-2">公開設定</div>
                      <input
                        className="w-full px-3 py-2 rounded border border-gray-700 bg-[#111113] text-white"
                        readOnly
                        value="公開する"
                      />
                    </div>
                    <div>
                      <div className="text-gray-400 text-xs mb-2">公開設定</div>
                      <input
                        className="w-full px-3 py-2 rounded border border-gray-700 bg-[#111113] text-white"
                        readOnly
                        value="公開する"
                      />
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <div className="text-gray-400 text-xs mb-2">本文</div>
                      <div className="bg-[#111113] border border-gray-700 rounded-md p-2">
                        <DiffDisplay
                          originalText={file.ancestorText || ''}
                          currentText={file.baseText || ''}
                          isMarkdown={true}
                        />
                      </div>
                    </div>
                    <div>
                      <div className="text-gray-400 text-xs mb-2">本文</div>
                      <div className="bg-[#111113] border border-gray-700 rounded-md p-2">
                        <DiffDisplay
                          originalText={file.ancestorText || ''}
                          currentText={file.headText || ''}
                          isMarkdown={true}
                        />
                      </div>
                    </div>
                  </div>
                </div>
              ))}

              <div className="flex justify-center">
                <button
                  className="px-6 py-2 bg-[#3832A5] hover:bg-blue-600 text-white rounded-md"
                  onClick={() => navigate(`/admin/change-suggestions/${id}`)}
                >
                  保存
                </button>
              </div>
            </div>
          )}
        </div>
      </div>
    </AdminLayout>
  );
};

export default ConflictResolutionPage;
