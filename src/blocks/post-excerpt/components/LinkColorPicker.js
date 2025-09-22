import { ColorPalette } from "@wordpress/components";
import { useEditorSettings } from "../../../scripts/template-editor/hooks/useEditorSettings";

export default function LinkColorPicker({ value, onChange }) {
  const { settings } = useEditorSettings();

  return (
    <div style={{ marginBottom: "16px" }}>
      <label
        style={{
          display: "block",
          marginBottom: "8px",
          fontWeight: "bold",
        }}
      >
        Link Color
      </label>
      <ColorPalette
        colors={settings?.colors || []}
        value={value}
        onChange={onChange}
        disableCustomColors={false}
        clearable={true}
      />
    </div>
  );
}
