import { API_CONFIG } from './config';

export const apiClient = {
  /**
   * APIリクエストを送信する汎用関数
   */
  async request(endpoint: string, options: RequestInit = {}) {
    const url = `${API_CONFIG.BASE_URL}${endpoint}`;

    const defaultHeaders = {
      'Content-Type': 'application/json',
    };
    
    const config = {
      ...options,
      credentials: 'include' as const,
      headers: {
        ...defaultHeaders,
        ...options.headers,
      },
    };
    
    try {
      const response = await fetch(url, config);
      
      // レスポンスをJSONとしてパース
      const data = await response.json();
      
      if (!response.ok) {
        throw new Error(data.error || `Error: ${response.status}`);
      }
      
      return data;
    } catch (error) {
      console.error('API request failed:', error);
      throw error;
    }
  },
  
  /**
   * 各種HTTPメソッド用のヘルパー関数
   */
  async get(endpoint: string, options = {}) {
    return this.request(endpoint, { ...options, method: 'GET' });
  },
  
  async post(endpoint: string, body: any, options = {}) {
    console.log('POSTリクエスト:', endpoint, body);
    return this.request(endpoint, {
      ...options,
      method: 'POST',
      body: JSON.stringify(body),
    });
  },
  
  async put(endpoint: string, body: any, options = {}) {
    return this.request(endpoint, {
      ...options,
      method: 'PUT',
      body: JSON.stringify(body),
    });
  },
  
  async delete(endpoint: string, options = {}) {
    return this.request(endpoint, { ...options, method: 'DELETE' });
  },
};
