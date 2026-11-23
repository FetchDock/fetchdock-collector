/**
 * Privacy first: the browser may only contact this origin (and, on the maintainer pages, the IdP). This blocks
 * telemetry, trackers and CDN requests from any dependency, including ones added later, which would leak the visitor's
 * IP address and usage to third parties. Scripts and styles aren't restricted: Next.js inlines both.
 * CommonJS, since next.config.js uses it for the pages without an IdP.
 *
 * @param {string | null} [issuer] The OIDC issuer, whose origin the maintainer pages must reach
 * @returns {string}
 */
const contentSecurityPolicy = (issuer = null) => {
  let idp = "";
  try {
    idp = issuer ? ` ${new URL(issuer).origin}` : "";
  } catch {
    // Invalid issuer: the login page reports it
  }

  return [
    `connect-src 'self'${idp}`,
    "img-src 'self' data: blob:",
    "font-src 'self' data:",
    "media-src 'self'",
    "frame-src 'none'",
    "object-src 'none'",
    "base-uri 'self'",
  ].join("; ");
};

module.exports = { contentSecurityPolicy };
