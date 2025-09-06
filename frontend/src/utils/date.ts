// 日付を「YYYY/M/D H:MM」形式で返す共通関数
export function formatDateTime(dateString: string): string {
  // すでに "2025/7/6 17:21" 形式ならそのまま返す
  if (/^\d{4}\/\d{1,2}\/\d{1,2} \d{1,2}:\d{2}$/.test(dateString)) {
    return dateString;
  }
  const d = new Date(dateString);
  if (isNaN(d.getTime())) return dateString;
  const yyyy = d.getFullYear();
  const m = d.getMonth() + 1;
  const dd = d.getDate();
  const hh = d.getHours();
  const mm = d.getMinutes().toString().padStart(2, '0');
  return `${yyyy}/${m}/${dd} ${hh}:${mm}`;
}
