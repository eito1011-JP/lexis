import { sessionService } from '../../../src/services/sessionService';

export interface AuthUser {
  userId: number;
  email: string;
}

export const getAuthenticatedUser = async (sessionId: string): Promise<AuthUser | null> => {
  try {
    if (!sessionId) {
      return null;
    }

    const user = await sessionService.getSessionUser(sessionId);
    return user;
  } catch (error) {
    console.error('ユーザー情報の取得エラー:', error);
    return null;
  }
};

export const isProduction = (): boolean => {
  return process.env.NODE_ENV === 'production';
};

export const requiresAuth = (): boolean => {
  return isProduction();
}; 