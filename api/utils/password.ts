import bcrypt from 'bcrypt';

/**
 * パスワードをハッシュ化する
 * @param password ハッシュ化する平文パスワード
 * @returns ハッシュ化されたパスワード
 */
export async function hashPassword(password: string): Promise<string> {
  const saltRounds = 10;
  return await bcrypt.hash(password, saltRounds);
}

/**
 * パスワードを検証する
 * @param password 検証する平文パスワード
 * @param hashedPassword 比較するハッシュ化されたパスワード
 * @returns パスワードが一致する場合はtrue、それ以外はfalse
 */
export async function verifyPassword(password: string, hashedPassword: string): Promise<boolean> {
  return await bcrypt.compare(password, hashedPassword);
}

/**
 * パスワードの強度をチェックする
 * @param password チェックする平文パスワード
 * @returns パスワードが要件を満たす場合はtrue、それ以外はfalse
 */
export function isStrongPassword(password: string): boolean {
  // 最低8文字
  if (password.length < 8) {
    return false;
  }

  // 必要に応じて追加の強度要件をここに追加
  // 例: 大文字、小文字、数字、特殊文字を含むなど

  return true;
}
