import { cp, rm } from "node:fs/promises";
import { fileURLToPath } from "node:url";
import path from "node:path";

const projectRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
const source = path.join(projectRoot, "assets");
const destination = path.join(projectRoot, "public", "assets");

await rm(destination, { recursive: true, force: true });
await cp(source, destination, { recursive: true });

console.log("Assets preparados em public/assets para o CDN da Vercel.");
