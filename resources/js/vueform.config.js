import en from '@vueform/vueform/locales/en'
import zh_TW from '@vueform/vueform/locales/zh_TW'
import es from '@vueform/vueform/locales/es'
import fr from '@vueform/vueform/locales/fr'
import ptBr from '@vueform/vueform/locales/pt_BR'
import ru from '@vueform/vueform/locales/ru'
import tailwind from '@vueform/vueform/dist/tailwind'
import { defineConfig } from '@vueform/vueform'
import VerticalFormTabs from './Pages/components/elements/VerticalFormTabs.vue'
import VerticalFormTab from './Pages/components/elements/VerticalFormTab.vue'

// These upstream accessibility labels still contain English.
es.vueform.a11y.list.remove = 'Botón para eliminar el elemento'
ptBr.vueform.a11y.list.remove = 'Botão para remover o item'
ru.vueform.a11y.list.remove = 'Удалить элемент'
ru.vueform.a11y.file.description = 'Нажмите Backspace, чтобы удалить'
ru.vueform.elements.list.add = '+ Добавить'

export default defineConfig({
  theme: tailwind,
  locales: { en, zh_TW, 'es-419': es, fr, 'pt-br': ptBr, ru },
  locale: 'en',
  classHelpers: true,
  templates: {
    FormTabs_vertical: VerticalFormTabs,
    FormTab_vertical: VerticalFormTab,
  }

})
