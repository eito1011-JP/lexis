/**
 * コミット作成APIエンドポイント
 */

export type Methods = {
  post: {
    reqBody: {
      pull_request_id: number;
      message: string;
    };
    resBody: {
      success: boolean;
      message?: string;
    };
  };
};

