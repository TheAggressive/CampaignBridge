/**
 * Retrieves a URL parameter value from the current page URL.
 *
 * @param {string} key - The parameter key to retrieve
 * @return {string|null} The parameter value, or null if not found
 */
export const getParam = (key: string): string | null => {
  return new URLSearchParams(window.location.search).get(key);
};

/**
 * Sets a URL parameter and reloads the page with the updated URL.
 *
 * If the value is null or undefined, the parameter is removed from the URL.
 * Otherwise, the parameter is set to the string representation of the value.
 *
 * @param {string} key - The parameter key to set
 * @param {string|number|null|undefined} value - The parameter value (null/undefined to remove the parameter)
 */
export const setParamAndReload = (
  key: string,
  value: string | number | null | undefined
): void => {
  const url = new URL(window.location.href);
  if (value == null) {
    url.searchParams.delete(key);
  } else {
    url.searchParams.set(key, String(value));
  }
  window.location.replace(url.toString());
};
