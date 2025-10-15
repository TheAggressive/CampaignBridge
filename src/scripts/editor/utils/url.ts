/**
 * Retrieves a URL parameter value from the current page URL.
 *
 * @param {string} k - The parameter key to retrieve
 * @return {string|null} The parameter value, or null if not found
 */
export const getParam = k => new URLSearchParams(window.location.search).get(k);
/**
 * Sets a URL parameter and reloads the page with the updated URL.
 *
 * If the value is null or undefined, the parameter is removed from the URL.
 * Otherwise, the parameter is set to the string representation of the value.
 *
 * @param {string} k - The parameter key to set
 * @param {any}    v - The parameter value (null/undefined to remove the parameter)
 */
export const setParamAndReload = (k, v) => {
  const url = new URL(window.location.href);
  if (v == null) {
    url.searchParams.delete(k);
  } else {
    url.searchParams.set(k, String(v));
  }
  window.location.replace(url.toString());
};
