/** Resolve explicit proportions and share remaining space among automatic columns. */
export function columnWidths(values: (number | undefined)[]): number[] {
  const total = values.reduce<number>((sum, value) => sum + (value ?? 0), 0);
  const automatic = values.filter(value => value === undefined).length;
  const share = automatic
    ? Math.max(0, 100 - total) / automatic || 100 / values.length
    : 0;
  const weights = values.map(value => value ?? share);
  const sum = weights.reduce((a, b) => a + b, 0);
  return weights.map(value => (value / sum) * 100);
}
