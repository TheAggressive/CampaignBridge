export type SpacingSide = 'top' | 'right' | 'bottom' | 'left';
export type SpacingInput = Partial<Record<SpacingSide, string | number>>;
export type NormalizedSpacing = Record<SpacingSide, number>;

const SIDES: SpacingSide[] = ['top', 'right', 'bottom', 'left'];

export const normalizeSpacing = (
  values: SpacingInput | undefined
): NormalizedSpacing =>
  Object.fromEntries(
    SIDES.map(side => [
      side,
      Math.max(
        0,
        Math.min(96, Number.parseInt(String(values?.[side] ?? ''), 10) || 0)
      ),
    ])
  ) as NormalizedSpacing;

export const toControlSpacing = (
  values: SpacingInput | undefined
): Record<SpacingSide, string> =>
  Object.fromEntries(
    SIDES.map(side => [side, `${values?.[side] ?? 0}px`])
  ) as Record<SpacingSide, string>;
