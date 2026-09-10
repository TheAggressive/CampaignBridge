/** Return the message supplied by WordPress apiFetch when one is available. */
export function requestErrorMessage(caught: unknown, fallback: string): string {
  if (caught instanceof Error && caught.message.trim()) {
    return caught.message;
  }

  if (
    typeof caught === 'object' &&
    caught !== null &&
    'message' in caught &&
    typeof caught.message === 'string' &&
    caught.message.trim()
  ) {
    return caught.message;
  }

  return fallback;
}
