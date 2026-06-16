import { createServer } from 'vite';
import { fileURLToPath } from 'url';
import { dirname } from 'path';

const __dirname = dirname(fileURLToPath(import.meta.url));
process.chdir(__dirname);

async function main() {
  try {
    const server = await createServer({
      root: __dirname,
      server: { port: parseInt(process.env.PORT || '4321'), host: true, strictPort: true }
    });
    await server.listen();
    server.printUrls();
  } catch (err) {
    console.error('Failed to start:', err);
    process.exit(1);
  }
}

main();
