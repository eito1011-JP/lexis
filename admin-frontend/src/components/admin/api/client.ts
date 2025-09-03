import { API_CONFIG } from './config';

export const apiClient = {
  /**
   * APIリクエストを送信する汎用関数
   */
  async request(endpoint: string, options: RequestInit = {}) {
    const url = `${API_CONFIG.BASE_URL}${endpoint}`;

    const defaultHeaders = {
      'Content-Type': 'application/json',
      Accept: 'application/json',
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

      // レスポンスの内容タイプをチェック
      const contentType = response.headers.get('content-type');
      if (!contentType || !contentType.includes('application/json')) {
        // APIは常にJSONを返す前提。非JSONはエラーとして扱う
        const error = new Error(
          `Unexpected non-JSON response: ${response.status} ${response.statusText}`
        );
        (error as any).response = {
          status: response.status,
          data: { message: 'Non-JSON response received from server' },
        };
        throw error;
      }

      // レスポンスをJSONとしてパース
      const data = await response.json();

      if (!response.ok) {
        const error = new Error(data.error || `Error: ${response.status}`);
        (error as any).response = {
          status: response.status,
          data: data
        };
        throw error;
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
  async get(endpoint: string, options: { params?: Record<string, any> } = {}) {
    let url = endpoint;

    // クエリパラメータがある場合はURLに追加
    if (options.params) {
      const searchParams = new URLSearchParams();
      Object.entries(options.params).forEach(([key, value]) => {
        if (value !== undefined && value !== null) {
          searchParams.append(key, String(value));
        }
      });
      const queryString = searchParams.toString();
      if (queryString) {
        url += `?${queryString}`;
      }
    }

    return this.request(url, { method: 'GET' });
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

  async patch(endpoint: string, body: any = {}, options = {}) {
    return this.request(endpoint, {
      ...options,
      method: 'PATCH',
      body: JSON.stringify(body),
    });
  },

  async delete(endpoint: string, options = {}) {
    return this.request(endpoint, { ...options, method: 'DELETE' });
  },
};
