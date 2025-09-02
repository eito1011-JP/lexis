import React from 'react';
import AdminLayout from '@/components/admin/layout';

export default function VerifyEmailPage(): React.ReactElement {
  return (
    <AdminLayout title="メールアドレス認証" sidebar={false}>
      <div className="bg-black min-h-screen flex items-center justify-center">
        <div className="w-full max-w-xl bg-[#0A0A0A] border-[1px] border-[#B1B1B1] rounded-xl p-8 pt-[3rem] text-center">
          <h1 className="text-white text-3xl font-bold mb-6">Lexis</h1>
          <h2 className="text-white text-2xl font-bold mb-4">メールアドレス認証を行って下さい</h2>
          <p className="text-[#B1B1B1] text-base">ご入力いただいたアドレスにメールを送信しました</p>
        </div>
      </div>
    </AdminLayout>
  );
}
