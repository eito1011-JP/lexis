import React from 'react';
import AdminLayout from '@site/src/components/admin/layout';
import { useSessionCheck } from '@site/src/hooks/useSessionCheck';

export default function DocumentsPage() {
  useSessionCheck('/admin/signup', false);

  return (
    <AdminLayout title="ドキュメント" sidebar={true}>
      <div className="p-6">
        <h1 className="text-2xl font-bold text-white mb-6">ドキュメント一覧</h1>
        <div className="bg-[#0A0A0A] rounded-lg p-6">
          <p className="text-white">ここにドキュメント一覧が表示されます</p>
        </div>
      </div>
    </AdminLayout>
  );
}
