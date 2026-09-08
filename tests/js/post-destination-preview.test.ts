import {
  isHttpsUrl,
  normalizeDestination,
  resolveDestinationPreview,
} from '../../src/blocks/shared/post-destination';

describe('resolveDestinationPreview', () => {
  describe('previewUrl resolution', () => {
    it('returns customUrl when destination is custom', () => {
      const result = resolveDestinationPreview({
        destination: 'custom',
        customUrl: 'https://example.com/custom',
        articleUrl: 'https://example.com/article',
        postParentUrl: 'https://example.com/parent',
        postTypeArchiveUrl: 'https://example.com/archive',
      });

      expect(result.previewUrl).toBe('https://example.com/custom');
      expect(result.destinationHelp).toBe('');
    });

    it('returns postParentUrl when destination is postParent and safe', () => {
      const result = resolveDestinationPreview({
        destination: 'postParent',
        customUrl: 'https://example.com/custom',
        articleUrl: 'https://example.com/article',
        postParentUrl: 'https://example.com/parent',
        postTypeArchiveUrl: 'https://example.com/archive',
      });

      expect(result.previewUrl).toBe('https://example.com/parent');
      expect(result.destinationHelp).toBe('');
    });

    it('returns postTypeArchiveUrl when destination is postTypeArchive and safe', () => {
      const result = resolveDestinationPreview({
        destination: 'postTypeArchive',
        customUrl: 'https://example.com/custom',
        articleUrl: 'https://example.com/article',
        postParentUrl: 'https://example.com/parent',
        postTypeArchiveUrl: 'https://example.com/archive',
      });

      expect(result.previewUrl).toBe('https://example.com/archive');
      expect(result.destinationHelp).toBe('');
    });

    it('returns articleUrl when destination is article and safe', () => {
      const result = resolveDestinationPreview({
        destination: 'article',
        customUrl: 'https://example.com/custom',
        articleUrl: 'https://example.com/article',
        postParentUrl: 'https://example.com/parent',
        postTypeArchiveUrl: 'https://example.com/archive',
      });

      expect(result.previewUrl).toBe('https://example.com/article');
      expect(result.destinationHelp).toBe('');
    });

    it('falls back to articleUrl when destination-specific URL is unsafe', () => {
      const result = resolveDestinationPreview({
        destination: 'postParent',
        customUrl: '',
        articleUrl: 'https://example.com/article',
        postParentUrl: 'javascript:void(0)',
        postTypeArchiveUrl: '',
      });

      expect(result.previewUrl).toBe('https://example.com/article');
      expect(result.destinationHelp).toBe('');
    });

    it('returns empty previewUrl when no candidate is safe', () => {
      const result = resolveDestinationPreview({
        destination: 'article',
        customUrl: '',
        articleUrl: '',
        postParentUrl: '',
        postTypeArchiveUrl: '',
      });

      expect(result.previewUrl).toBe('');
      expect(result.destinationHelp).not.toBe('');
    });
  });

  describe('destinationHelp messages', () => {
    it('uses the custom-URL-required message when destination is custom with no preview', () => {
      const result = resolveDestinationPreview({
        destination: 'custom',
        customUrl: '',
        articleUrl: 'https://example.com/article',
        postParentUrl: '',
        postTypeArchiveUrl: '',
      });

      expect(result.previewUrl).toBe('');
      expect(result.destinationHelp).toBe(
        'Enter a custom absolute URL to preview it.'
      );
    });

    it('uses the no-absolute-URL message for non-custom destinations', () => {
      const result = resolveDestinationPreview({
        destination: 'article',
        customUrl: '',
        articleUrl: '',
        postParentUrl: '',
        postTypeArchiveUrl: '',
      });

      expect(result.previewUrl).toBe('');
      expect(result.destinationHelp).toBe(
        'This post snapshot has no absolute URL yet; the link renders from the post data at send time.'
      );
    });

    it('honours caller-provided help messages', () => {
      const result = resolveDestinationPreview({
        destination: 'custom',
        customUrl: '',
        articleUrl: '',
        postParentUrl: '',
        postTypeArchiveUrl: '',
        helpMessages: {
          customUrlRequired: 'Enter a custom absolute URL to preview it.',
        },
      });

      expect(result.previewUrl).toBe('');
      expect(result.destinationHelp).toBe(
        'Enter a custom absolute URL to preview it.'
      );
    });
  });

  describe('normalization', () => {
    it('normalizes unknown destination values to article', () => {
      expect(normalizeDestination('bogus')).toBe('article');
      expect(normalizeDestination(undefined)).toBe('article');
      expect(normalizeDestination('article')).toBe('article');
      expect(normalizeDestination('postParent')).toBe('postParent');
    });

    it('isHttpsUrl only accepts absolute HTTP or HTTPS URLs', () => {
      expect(isHttpsUrl('https://example.com')).toBe(true);
      expect(isHttpsUrl('http://example.com')).toBe(true);
      expect(isHttpsUrl('HTTP://EXAMPLE.COM/path?q=1')).toBe(true);
      expect(isHttpsUrl('http://localhost:8882/posts/7')).toBe(true);

      // Dangerous and non-HTTP(S) schemes must be rejected.
      expect(isHttpsUrl('javascript:void(0)')).toBe(false);
      expect(isHttpsUrl('JavaScript:alert(1)')).toBe(false);
      expect(isHttpsUrl('data:text/html,<script>alert(1)</script>')).toBe(
        false
      );
      expect(isHttpsUrl('vbscript:msgbox(1)')).toBe(false);
      expect(isHttpsUrl('mailto:example@example.com')).toBe(false);

      // Relative, protocol-relative, and malformed values must be rejected.
      expect(isHttpsUrl('not-a-url')).toBe(false);
      expect(isHttpsUrl('example.com/a')).toBe(false);
      expect(isHttpsUrl('//example.com/a')).toBe(false);
      expect(isHttpsUrl('/relative/path')).toBe(false);
      expect(isHttpsUrl('')).toBe(false);
    });
  });
});
