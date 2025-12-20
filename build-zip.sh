#!/bin/bash

# 🚨 Validar parámetro
if [ -z "$1" ]; then
  echo "❌ Uso: ./build-zip.sh <nombre_carpeta_X>"
  echo "👉 Ejemplo: ./build-zip.sh Banchile"
  exit 1
fi

# Variables base
X_FOLDER="$1"
CURRENT_FOLDER="$(pwd)"
MODULE_NAME_VR="$(basename "$CURRENT_FOLDER")"
BUILD_DIR="${CURRENT_FOLDER}/${MODULE_NAME_VR}-build"
DEST_DIR="${BUILD_DIR}/${X_FOLDER}/Payments"
USER_ID=$(id -u)

echo "🧩 Preparando build para: $MODULE_NAME_VR"
echo "📁 Estructura final: ${X_FOLDER}/Payments/"

# 🧹 Limpiar y copiar estructura
rm -rf "$BUILD_DIR" \
  && mkdir -p "$DEST_DIR" \
  && cp -pr "$CURRENT_FOLDER/"* "$DEST_DIR"

# 🚿 Limpiar archivos innecesarios dentro del destino
cd "$DEST_DIR" \
  && find . -type d -name ".git*" -exec rm -rf {} + \
  && rm -rf \
      .git* \
      .idea \
      Makefile \
      .env* \
      env \
      .docker* \
      docker* \
      *.md \
      *.txt \
      *.sh \
      vendor \
      node_modules

# 📦 Crear ZIP (sin incluir la carpeta -build)
cd "$BUILD_DIR"
zip -r -q -o "${CURRENT_FOLDER}/${MODULE_NAME_VR}-${X_FOLDER}.zip" "${X_FOLDER}"

# 🔒 Permisos y limpieza
cd "$CURRENT_FOLDER"
chown "$USER_ID":"$USER_ID" "${MODULE_NAME_VR}-${X_FOLDER}.zip" 2>/dev/null || true
chmod 644 "${MODULE_NAME_VR}-${X_FOLDER}.zip"
rm -rf "$BUILD_DIR"

echo "✅ ZIP generado: ${MODULE_NAME_VR}-${X_FOLDER}.zip"
echo "📁 Al descomprimir tendrás: ${X_FOLDER}/Payments/"
