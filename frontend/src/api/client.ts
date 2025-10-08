import aspida from '@aspida/axios';
import axios from 'axios';
import api from '@/api/$api';

/**
 * Aspida用のaxiosインスタンスを作成
 */
const axiosInstance = axios.create({
  baseURL: '/api', // プロキシを使用するため/apiをベースURLに設定
  withCredentials: true, // クッキーを送信
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
});

/**
 * リクエストインターセプター: トークンを自動的に追加
 */
axiosInstance.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('access_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

/**
 * レスポンスインターセプター: エラーハンドリング
 */
axiosInstance.interceptors.response.use(
  (response) => response,
  (error) => {
    console.error('API request failed:', error);
    return Promise.reject(error);
  }
);

/**
 * Aspidaクライアントのエクスポート
 */
export const client = api(aspida(axiosInstance));

/**
 * axiosインスタンスのエクスポート（aspidaの型定義がないエンドポイント用）
 */
export { axiosInstance as axios };

