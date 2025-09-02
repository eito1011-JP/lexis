import React from 'react';
import AdminLayout from '@/components/admin/layout';

export default function OrganizationJoinPage(): React.ReactElement {
  return (
    <AdminLayout title="組織に参加" sidebar={false}>
      <div className="bg-black min-h-screen flex items-center justify-center">
        <div className="w-full max-w-[600px] bg-[#0A0A0A] border-[1px] border-[#B1B1B1] rounded-xl p-12">
          <h2 className="text-white text-2xl font-bold text-center">既存の組織に参加</h2>
          <p className="text-[#B1B1B1] text-center mt-6">この画面は今後実装予定です。</p>
        </div>
      </div>
    </AdminLayout>
  );
}


