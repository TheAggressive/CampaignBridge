import { truncateExcerpt } from '../../src/blocks/post-excerpt/truncate';

describe('truncateExcerpt', () => {
  it('returns the full text when it is within the word budget', () => {
    expect(truncateExcerpt('one two three', 10)).toBe('one two three');
  });

  it('caps at maxWords words and appends an ellipsis only when cut', () => {
    const text = 'one two three four five six';
    expect(truncateExcerpt(text, 3)).toBe('one two three…');
  });

  it('does not double the ellipsis when the source already ends with one', () => {
    const text = 'one two three four five…';
    expect(truncateExcerpt(text, 3)).toBe('one two three…');
  });

  it('counts the trailing ellipsis of an auto-excerpt as a word, not as budget', () => {
    const words = Array.from({ length: 55 }, (_, i) => `w${i + 1}`).join(' ');
    const source = `${words}…`;
    // 55 real words + a trailing "…" must still cap to exactly maxWords words.
    expect(
      truncateExcerpt(source, 50).replace(/…$/, '').split(' ')
    ).toHaveLength(50);
  });

  it('decodes entities and strips markup before counting words', () => {
    expect(truncateExcerpt('<p>one&nbsp;two <b>three</b></p>', 2)).toBe(
      'one two…'
    );
  });
});
