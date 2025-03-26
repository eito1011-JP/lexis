import AdminLayout from '@site/src/components/admin/layout';
import React from 'react';

export default function AdminPage(): JSX.Element {
  return (
    <AdminLayout title="メディア" sidebar={false}>
      <body className="bg-black min-h-screen flex items-center justify-center">
          <div className="w-full max-w-md bg-[#0A0A0A] border-[1px] border-[#B1B1B1]  rounded-xl p-8 pt-[3rem]">
              <div className="text-center mb-8">
                  <h1 className="text-white text-3xl font-bold mb-2">Lexis</h1>
                  <h2 className="text-white text-2xl">新規登録</h2>
              </div>
              
              <form className='mb-[1rem]'>
                  <div className="mb-2">
                      <label for="email" className="block text-white mb-1 font-bold">メールアドレス</label>
                      <input 
                          type="email" 
                          id="email" 
                          placeholder="mail@example.com"
                          className="w-full px-4 py-4 rounded-lg bg-white text-black placeholder-[#737373] focus:outline-none"
                      />
                  </div>
                  
                  <div className="mb-8">
                      <label for="password" className="block text-white mb-1 font-bold">パスワード</label>
                      <input 
                          type="password" 
                          id="password" 
                          placeholder="パスワードを入力"
                          className="w-full px-4 py-4 rounded-lg bg-white text-black placeholder-[#737373] focus:outline-none"
                      />
                  </div>
                  
                  <button 
                      type="submit" 
                      className="border-none w-full font-bold bg-[#3832A5] hover:bg-indigo-800 text-white py-4 rounded-lg text-center transition duration-200"
                  >
                      登録する
                  </button>
              </form>
          </div>
      </body>
    </AdminLayout>
  );
}
