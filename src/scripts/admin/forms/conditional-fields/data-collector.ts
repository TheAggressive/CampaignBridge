/**
 * Collects form data for conditional evaluation, matching current behavior.
 */

export class ConditionalDataCollector {
  constructor(
    private form: HTMLFormElement,
    private formId: string
  ) {}

  public getFormData(): Record<string, string> {
    const data: Record<string, string> = {};
    const inputs = this.form.querySelectorAll('input, select, textarea');

    const checkboxNames = new Set<string>();

    inputs.forEach(el => {
      const input = el as HTMLInputElement;
      if (input.type === 'checkbox' && input.name) {
        checkboxNames.add(input.name);
      }
    });

    inputs.forEach(el => {
      const input = el as
        | HTMLInputElement
        | HTMLSelectElement
        | HTMLTextAreaElement;
      const fullName = (input as HTMLInputElement).name;

      if (
        (input as HTMLInputElement).type === 'hidden' &&
        checkboxNames.has(fullName)
      ) {
        return;
      }

      let value: any = (input as HTMLInputElement).value;

      if ((input as HTMLInputElement).type === 'checkbox') {
        value = (input as HTMLInputElement).checked ? '1' : '0';
      } else if ((input as HTMLInputElement).type === 'radio') {
        if (!(input as HTMLInputElement).checked) {
          return;
        }
      }

      if (fullName) {
        const fieldId = this.parseFieldName(fullName);
        if (fieldId) {
          data[fieldId] = String(value);
        }
      }
    });

    return data;
  }

  private parseFieldName(fullName: string): string | null {
    const match = fullName.match(new RegExp(`^${this.formId}\\[(.+)\\]$`));
    if (match) {
      return match[1];
    }
    return fullName;
  }
}
