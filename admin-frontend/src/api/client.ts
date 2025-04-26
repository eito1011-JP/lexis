import axios from 'axios';

const baseURL = '/api';

const apiClient = {
  async get(endpoint: string) {
    try {
      const response = await axios.get(`${baseURL}${endpoint}`);
      return response.data;
    } catch (error) {
      console.error('API GET error:', error);
      throw error;
    }
  },
  
  async post(endpoint: string, data: any) {
    try {
      const response = await axios.post(`${baseURL}${endpoint}`, data);
      return response.data;
    } catch (error) {
      console.error('API POST error:', error);
      throw error;
    }
  },
  
  async put(endpoint: string, data: any) {
    try {
      const response = await axios.put(`${baseURL}${endpoint}`, data);
      return response.data;
    } catch (error) {
      console.error('API PUT error:', error);
      throw error;
    }
  },
  
  async delete(endpoint: string) {
    try {
      const response = await axios.delete(`${baseURL}${endpoint}`);
      return response.data;
    } catch (error) {
      console.error('API DELETE error:', error);
      throw error;
    }
  }
};

export { apiClient }; 