import express from 'express';
import proxy from 'express-http-proxy';

// プロキシサーバーの設定
const app = express();

// /admin/* へのリクエストを 3002 ポートにプロキシする
app.use('/admin', proxy('localhost:3002', {
  proxyReqPathResolver: (req) => {
    // /admin + /xyz をそのまま転送（admin-frontendのbaseが/admin/なので）
    return '/admin' + req.url;
  }
}));

// その他のリクエストをDocusaurusサーバーにプロキシ
// ここでは何もしない（このプロキシサーバー自体が3000ポートで動くため）

// プロキシサーバーを起動
const server = app.listen(3000, () => {
  console.log('🚀 統合プロキシサーバーを起動しました: http://localhost:3000');
  console.log('  - 管理画面: http://localhost:3000/admin');
});

// Ctrl+Cで終了したらサーバーを停止
process.on('SIGINT', () => {
  console.log('👋 プロキシサーバーを停止します...');
  server.close();
  process.exit(0);
}); 