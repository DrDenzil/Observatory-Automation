#!/bin/bash
cd "$(dirname "$0")"
exec npx vite --port "${PORT:-5173}"
