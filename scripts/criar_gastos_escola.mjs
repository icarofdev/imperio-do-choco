import fs from "node:fs/promises";
import path from "node:path";
import { SpreadsheetFile, Workbook } from "@oai/artifact-tool";

const outputDir = path.resolve("outputs", "gastos-escola");
await fs.mkdir(outputDir, { recursive: true });

const workbook = Workbook.create();
const sheet = workbook.worksheets.add("Gastos da Semana");
sheet.showGridLines = false;

sheet.getRange("A1:E1").merge();
sheet.getRange("A1").values = [["Gastos da semana na escola"]];
sheet.getRange("A1").format = {
  fill: "#1F6F5B",
  font: { bold: true, color: "#FFFFFF", size: 16 },
  horizontalAlignment: "center",
  verticalAlignment: "center",
};
sheet.getRange("A1:E1").format.rowHeightPx = 36;

sheet.getRange("A3:E3").values = [["Dia", "Lanche", "Transporte", "Material", "Total do dia"]];
sheet.getRange("A3:E3").format = {
  fill: "#DCEFE8",
  font: { bold: true, color: "#143C32" },
  horizontalAlignment: "center",
  verticalAlignment: "center",
  borders: {
    bottom: { style: "Continuous", color: "#1F6F5B" },
  },
};

sheet.getRange("A4:D8").values = [
  ["Segunda-feira", 8.5, 5, 2],
  ["Terca-feira", 7, 5, 0],
  ["Quarta-feira", 9, 5, 3.5],
  ["Quinta-feira", 6.5, 5, 1.5],
  ["Sexta-feira", 10, 5, 4],
];
sheet.getRange("E4:E8").formulas = [
  ["=SUM(B4:D4)"],
  ["=SUM(B5:D5)"],
  ["=SUM(B6:D6)"],
  ["=SUM(B7:D7)"],
  ["=SUM(B8:D8)"],
];

sheet.getRange("A10:D10").merge();
sheet.getRange("A10").values = [["Total da semana"]];
sheet.getRange("E10").formulas = [["=SUM(E4:E8)"]];

sheet.getRange("A4:E8").format = {
  borders: {
    top: { style: "Continuous", color: "#D9E2DD" },
    bottom: { style: "Continuous", color: "#D9E2DD" },
    left: { style: "Continuous", color: "#D9E2DD" },
    right: { style: "Continuous", color: "#D9E2DD" },
  },
  verticalAlignment: "center",
};
sheet.getRange("B4:E10").format.numberFormat = "R$ #,##0.00";
sheet.getRange("E4:E8").format = {
  fill: "#F6FBF9",
  font: { bold: true, color: "#143C32" },
};
sheet.getRange("A10:E10").format = {
  fill: "#1F6F5B",
  font: { bold: true, color: "#FFFFFF" },
  borders: {
    top: { style: "Continuous", color: "#143C32" },
    bottom: { style: "Continuous", color: "#143C32" },
  },
  verticalAlignment: "center",
};

sheet.getRange("A3:E10").format.wrapText = true;
sheet.getRange("A:A").format.columnWidthPx = 145;
sheet.getRange("B:E").format.columnWidthPx = 115;
sheet.getRange("A3:E10").format.rowHeightPx = 28;
sheet.freezePanes.freezeRows(3);

const inspect = await workbook.inspect({
  kind: "table",
  range: "Gastos da Semana!A1:E10",
  include: "values,formulas",
  tableMaxRows: 12,
  tableMaxCols: 6,
});
console.log(inspect.ndjson);

const errors = await workbook.inspect({
  kind: "match",
  searchTerm: "#REF!|#DIV/0!|#VALUE!|#NAME\\?|#N/A",
  options: { useRegex: true, maxResults: 50 },
  summary: "formula error scan",
});
console.log(errors.ndjson);

const preview = await workbook.render({
  sheetName: "Gastos da Semana",
  range: "A1:E10",
  scale: 1,
  format: "png",
});
await fs.writeFile(path.join(outputDir, "preview.png"), new Uint8Array(await preview.arrayBuffer()));

const output = await SpreadsheetFile.exportXlsx(workbook);
const outputPath = path.join(outputDir, "gastos_da_semana_na_escola.xlsx");
await output.save(outputPath);
console.log(`SAVED:${outputPath}`);
