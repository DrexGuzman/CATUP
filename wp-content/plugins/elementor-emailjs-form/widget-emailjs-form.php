<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Elementor_EmailJS_Form_Widget extends \Elementor\Widget_Base {

    public function get_name() { return 'emailjs_form'; }
    public function get_title() { return 'EmailJS Form'; }
    public function get_icon() { return 'eicon-mail'; }
    public function get_categories() { return [ 'general' ]; }

    protected function register_controls() {
        $this->start_controls_section('content_section', [
            'label' => 'Configuración EmailJS',
        ]);

        $this->add_control('service_id', [
            'label' => 'Service ID',
            'type'  => \Elementor\Controls_Manager::TEXT,
            'default' => '',
        ]);
        $this->add_control('template_id', [
            'label' => 'Template ID',
            'type'  => \Elementor\Controls_Manager::TEXT,
            'default' => '',
        ]);
        $this->add_control('public_key', [
            'label' => 'Public Key',
            'type'  => \Elementor\Controls_Manager::TEXT,
            'default' => '',
        ]);

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        ?>
        <form style="margin-bottom: 40px;" class="eef-emailjs-form"
              data-service="<?php echo esc_attr($settings['service_id']); ?>"
              data-template="<?php echo esc_attr($settings['template_id']); ?>"
              data-public="<?php echo esc_attr($settings['public_key']); ?>">
            <label >Nombre:</label>
            <input style="border: #2B3A42 0.5px solid; border-radius: 12px; color: #3d4041ff; margin-bottom: 12px;" placeholder="Nombre Apellidos" type="text" name="fullname" required>

            <label>Teléfono:</label>
            <input style="border: #2B3A42 0.5px solid; border-radius: 12px; color: #3d4041ff; margin-bottom: 12px;" placeholder="+506 8888-8888" type="text" name="phone" required>

            <label>Email:</label>
            <input style="border: #2B3A42 0.5px solid; border-radius: 12px; color: #3d4041ff; margin-bottom: 12px;" placeholder="correo@dominio.com" type="email" name="email" required>

            <label>Mensaje:</label>
            <textarea style="border: #2B3A42 0.5px solid; border-radius: 12px; color: #3d4041ff; margin-bottom: 12px;" placeholder="Escribe tu mensaje aquí..." name="message" required></textarea>

            <button type="submit">Enviar</button>
            <p class="eef-status"></p>
        </form>
        <?php
    }
}
