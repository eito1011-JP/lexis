export type UserBranchSessionRequest = {
  pull_request_id: number;
};

export type UserBranchSessionResponse = {
  success?: boolean;
};

export type Methods = {
  post: {
    reqBody: UserBranchSessionRequest;
    resBody: UserBranchSessionResponse;
  };
};

