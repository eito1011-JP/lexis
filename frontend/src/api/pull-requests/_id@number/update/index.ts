/**
 * PATCH /api/pull-requests/:id/update
 */
export type Methods = {
  patch: {
    reqBody: {
      title?: string;
      description?: string;
    };
    resBody: {
      success: boolean;
      message?: string;
    };
  };
};

