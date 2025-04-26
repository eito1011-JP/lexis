import axios from 'axios';

interface ApiResponse {
  message?: string;
  data?: any;
  status?: number;
}

class ApiClient {
  private baseUrl: string;

  constructor() {
    this.baseUrl = process.env.API_URL || '/api';
  }

  async get(endpoint: string): Promise<ApiResponse> {
    try {
      const response = await axios.get(`${this.baseUrl}${endpoint}`, {
        withCredentials: true,
      });
      return response.data;
    } catch (error) {
      if (axios.isAxiosError(error)) {
        throw new Error(error.response?.data?.message || '通信エラーが発生しました');
      }
      throw error;
    }
  }

  async post(endpoint: string, data?: any): Promise<ApiResponse> {
    try {
      const response = await axios.post(`${this.baseUrl}${endpoint}`, data, {
        withCredentials: true,
      });
      return response.data;
    } catch (error) {
      if (axios.isAxiosError(error)) {
        throw new Error(error.response?.data?.message || '通信エラーが発生しました');
      }
      throw error;
    }
  }

  async put(endpoint: string, data?: any): Promise<ApiResponse> {
    try {
      const response = await axios.put(`${this.baseUrl}${endpoint}`, data, {
        withCredentials: true,
      });
      return response.data;
    } catch (error) {
      if (axios.isAxiosError(error)) {
        throw new Error(error.response?.data?.message || '通信エラーが発生しました');
      }
      throw error;
    }
  }

  async delete(endpoint: string): Promise<ApiResponse> {
    try {
      const response = await axios.delete(`${this.baseUrl}${endpoint}`, {
        withCredentials: true,
      });
      return response.data;
    } catch (error) {
      if (axios.isAxiosError(error)) {
        throw new Error(error.response?.data?.message || '通信エラーが発生しました');
      }
      throw error;
    }
  }
}

export const apiClient = new ApiClient(); 