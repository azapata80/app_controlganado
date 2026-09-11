const { test, expect } = require('@playwright/test');

const baseURL = process.env.GANADERIA_TEST_URL || 'http://127.0.0.1:8080';
const username = process.env.GANADERIA_TEST_USER;
const password = process.env.GANADERIA_TEST_PASSWORD;

test.use({ launchOptions: { channel: 'chrome' } });

test('login se adapta de 320 px a escritorio', async ({ page }) => {
  for (const viewport of [{ width: 320, height: 800 }, { width: 390, height: 844 }, { width: 768, height: 1024 }, { width: 1366, height: 768 }]) {
    await page.setViewportSize(viewport);
    await page.goto(`${baseURL}/login.php`);
    await expect(page.locator('h1')).toContainText('Sistema de Gestión');
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBeTruthy();
    expect(await page.locator('button').evaluate(el => el.getBoundingClientRect().height)).toBeGreaterThanOrEqual(44);
  }
});

test('pantallas autenticadas no desbordan y el menú móvil funciona', async ({ page }) => {
  test.skip(!username || !password, 'Defina GANADERIA_TEST_USER y GANADERIA_TEST_PASSWORD.');
  await page.goto(`${baseURL}/login.php`);
  await page.getByLabel('Usuario').fill(username);
  await page.getByLabel('Contraseña').fill(password);
  await page.getByRole('button', { name: 'Ingresar' }).click();
  await expect(page.locator('h1')).toHaveText('Aplicaciones');
  await expect(page.getByRole('link', { name: /Panel de control/ })).toBeVisible();
  await expect(page.getByRole('link', { name: /Animales/ })).toBeVisible();
  await expect(page.getByRole('link', { name: /Catálogos/ })).toBeVisible();
  await expect(page.getByRole('link', { name: /Reportes/ })).toBeVisible();
  await expect(page.getByRole('link', { name: /Bodega/ })).toBeVisible();

  await page.setViewportSize({ width: 1366, height: 768 });
  const launcherRows = await page.locator('.app-grid').evaluateAll(grids => grids.map(grid => {
    const rows = new Map();
    grid.querySelectorAll('.app-tile').forEach(tile => {
      const top = Math.round(tile.getBoundingClientRect().top);
      rows.set(top, (rows.get(top) || 0) + 1);
    });
    return [...rows.values()];
  }));
  expect(launcherRows.flat().every(items => items <= 4)).toBeTruthy();

  for (const viewport of [{ width: 320, height: 800 }, { width: 390, height: 844 }, { width: 768, height: 1024 }, { width: 1366, height: 768 }]) {
    await page.setViewportSize(viewport);
    for (const route of ['apps.php', 'index.php', 'animals.php', 'costs.php', 'warehouse.php', 'data_reports.php', 'catalogs.php', 'users.php', 'help.php', 'manual.php']) {
      await page.goto(`${baseURL}/${route}`);
      const unsafe = await page.evaluate(() => [...document.querySelectorAll('body *')].filter(el => {
        const r=el.getBoundingClientRect();
        if(r.right<=window.innerWidth+1&&r.left>=-1)return false;
        for(let parent=el.parentElement;parent&&parent!==document.body;parent=parent.parentElement){
          const pr=parent.getBoundingClientRect();const overflow=getComputedStyle(parent).overflowX;
          if(['auto','scroll','hidden','clip'].includes(overflow)&&pr.left>=-1&&pr.right<=window.innerWidth+1)return false;
        }
        return true;
      }).slice(0,8).map(el => {const r=el.getBoundingClientRect();return {tag:el.tagName,className:String(el.className||''),right:Math.round(r.right)};}));
      expect(unsafe, `${route} a ${viewport.width}px tiene contenido sin contención`).toEqual([]);
    }
    if (viewport.width <= 760) {
      await page.goto(`${baseURL}/index.php`);
      const menu = page.locator('.menu-toggle');
      await expect(menu).toBeVisible();
      await menu.click();
      await expect(page.locator('#main-nav')).toHaveClass(/open/);
      await page.keyboard.press('Escape');
      await expect(page.locator('#main-nav')).not.toHaveClass(/open/);
    }
  }

  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(`${baseURL}/catalogs.php`);
  const usedLocation = page.locator('.catalog-table tr').filter({ has: page.locator('input[value="Montezuma"]') });
  await expect(usedLocation).toBeVisible();
  await expect(usedLocation.getByRole('button', { name: 'Eliminar' })).toHaveCount(0);
  const catalogName = `Corral prueba ${Date.now()}`;
  await page.locator('#locations-name').fill(catalogName);
  await page.locator('#locations-order').fill('95');
  await page.locator('.catalog-create').first().getByRole('button', { name: 'Agregar' }).click();
  await expect(page.getByRole('status')).toContainText('Se creó la ubicación');
  const catalogRow = page.locator('.catalog-table tr').filter({ has: page.locator(`input[value="${catalogName}"]`) });
  await expect(catalogRow).toBeVisible();
  page.once('dialog', dialog => dialog.accept());
  await catalogRow.getByRole('button', { name: 'Eliminar' }).click();
  await expect(page.getByRole('status')).toContainText('Se eliminó la ubicación permanentemente');
  await expect(page.locator('.catalog-table tr').filter({ has: page.locator(`input[value="${catalogName}"]`) })).toHaveCount(0);

  await page.goto(`${baseURL}/warehouse.php`);
  const productCode = `PRB-${Date.now()}`;
  const productForm = page.locator('.warehouse-layout section').nth(1);
  await productForm.getByLabel('Código').fill(productCode);
  await productForm.getByLabel('Nombre').fill('Producto de prueba');
  await productForm.getByLabel('Unidad').fill('unidad');
  await productForm.getByLabel('Existencia mínima').fill('3');
  await productForm.getByRole('button', { name: 'Crear producto' }).click();
  await expect(page.getByRole('status')).toContainText('Producto creado');
  const productOption = page.locator('#movement-product option').filter({ hasText: productCode });
  const productId = await productOption.getAttribute('value');
  await page.locator('#movement-product').selectOption(productId);
  await page.locator('#movement-quantity').fill('10');
  await page.locator('#movement-unit-cost').fill('1000');
  await page.locator('#movement-reference').fill('ENTRADA-PRUEBA');
  await page.locator('.warehouse-layout section').first().getByRole('button', { name: 'Registrar movimiento' }).click();
  await expect(page.getByRole('status')).toContainText('Movimiento de bodega registrado');
  await page.goto(`${baseURL}/costs.php`);
  await page.locator('#cost-source').selectOption('WAREHOUSE');
  await page.locator('#warehouse-product').selectOption(productId);
  await page.locator('#cost-quantity').fill('2');
  await page.getByRole('button', { name: 'Registrar costo' }).click();
  await expect(page.getByRole('status')).toContainText('Consumo descontado de bodega');
  await page.locator('#cost-source').selectOption('WAREHOUSE');
  await page.locator('#warehouse-product').selectOption(productId);
  await page.locator('#cost-quantity').evaluate(el => el.removeAttribute('max'));
  await page.locator('#cost-quantity').fill('9999');
  await page.getByRole('button', { name: 'Registrar costo' }).click();
  await expect(page.getByRole('alert')).toContainText('Existencia insuficiente');
  await page.goto(`${baseURL}/warehouse.php`);
  const stockRow = page.locator('.warehouse-products tbody tr').filter({ has: page.locator(`input[value="${productCode}"]`) });
  await expect(stockRow.locator('td').nth(5)).toContainText('8,00');

  await page.goto(`${baseURL}/help.php`);
  await page.getByLabel('¿Qué necesita hacer?').fill('importación');
  await expect(page.locator('.help-item:visible')).toHaveCount(1);
  await page.getByRole('link', { name: 'Abrir manual completo' }).click();
  await expect(page.locator('h1')).toHaveText('Manual de usuario');

  const dataReports = ['animals', 'weights', 'events', 'warehouse_stock', 'warehouse_movements', 'costs', 'labor', 'transfers', 'sales', 'closings'];
  for (const report of dataReports) {
    await page.goto(`${baseURL}/data_reports.php?report=${report}&scope=all`);
    await expect(page.locator('h1')).toHaveText('Reportes');
    const excelResponse = await page.request.get(`${baseURL}/exports/data_report.php?report=${report}&scope=all&format=excel`);
    expect(excelResponse.ok(), `Excel de ${report}`).toBeTruthy();
    expect(excelResponse.headers()['content-type']).toContain('application/vnd.ms-excel');
    expect((await excelResponse.body()).subarray(0, 5).toString()).toBe('<?xml');
    const pdfResponse = await page.request.get(`${baseURL}/exports/data_report.php?report=${report}&scope=all&format=pdf`);
    expect(pdfResponse.ok(), `PDF de ${report}`).toBeTruthy();
    expect(pdfResponse.headers()['content-type']).toContain('application/pdf');
    expect((await pdfResponse.body()).subarray(0, 5).toString()).toBe('%PDF-');
  }
  await page.goto(`${baseURL}/data_reports.php?report=weights&scope=range&from=2026-09-01&to=2026-09-30`);
  await expect(page.locator('.report-preview h3')).toHaveText('Pesajes');
  await expect(page.locator('.report-preview tbody tr')).not.toHaveCount(0);
  await page.goto(`${baseURL}/data_reports.php?report=weights&scope=range&from=incorrecta&to=2026-09-30`);
  await expect(page.getByRole('alert')).toContainText('rango de fechas válido');
});
