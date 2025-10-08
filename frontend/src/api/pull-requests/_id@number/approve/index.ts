/**
 * PATCH /api/pull-requests/:id/approve
 */
export type Methods = {
  patch: {
    resBody: {
      success: boolean;
      message?: string;
    };
  };
};

