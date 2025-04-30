export const ROUTES = {
  HOME: 'home',
  LOGIN: 'login',
  SIGNUP: 'signup',
  DOCUMENTS: 'documents',
  CREATE_DOCUMENT: 'create-document',
  EDIT_DOCUMENT: 'edit-document',
  DOCUMENT_BY_SLUG: 'document-by-slug',
} as const;

export const ROUTE_PATHS = {
  [ROUTES.HOME]: '/',
  [ROUTES.LOGIN]: '/login',
  [ROUTES.SIGNUP]: '/signup',
  [ROUTES.DOCUMENTS]: '/documents',
  [ROUTES.CREATE_DOCUMENT]: '/documents/create',
  [ROUTES.EDIT_DOCUMENT]: '/documents/:slug/edit',
  [ROUTES.DOCUMENT_BY_SLUG]: '/documents/:slug',
} as const;

export const generatePath = (routeName: keyof typeof ROUTE_PATHS, params?: Record<string, string>) => {
  let path = ROUTE_PATHS[routeName];
  if (params) {
    Object.entries(params).forEach(([key, value]) => {
      path = path.replace(`:${key}`, value);
    });
  }
  return path;
}; 