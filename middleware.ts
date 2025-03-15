import { next } from "@vercel/edge";

export const config = {
    matcher: ["/(.*)", "/api/:path*"],
  };

export default function middleware(request) {
  const authorizationHeader = request.headers.get("authorization");

  if (authorizationHeader) {
    const basicAuth = authorizationHeader.split(" ")[1];
    const [user, password] = atob(basicAuth).toString().split(":");

    // if (user == process.env.BASIC_AUTH_USER && password == process.env.BASIC_AUTH_PASSWORD) {
    //   return next();
    // }
    console.log(user, password);
    return next();
  }

  return new Response("Basic Auth required", {
    status: 401,
    headers: {
      "WWW-Authenticate": 'Basic realm="Secure Area"',
    },
  });
}
