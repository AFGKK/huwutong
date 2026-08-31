/**
 * D-31: 隐私政策 / 用户协议（提审用精简版）
 */
Page({
  data: {
    title: '',
    paragraphs: [],
    webUrl: '',
  },

  onLoad(options) {
    const type = options.type || 'privacy';
    if (type === 'terms') {
      this.setData({
        title: '用户协议',
        webUrl: 'https://88.huwutong.com/terms',
        paragraphs: [
          '欢迎使用互物通 HWT License 查询小程序。',
          '本小程序提供软件授权信息查询与设备激活辅助功能。您应合法持有 License Key，不得用于未授权用途。',
          '您理解并同意：查询结果仅供参考，最终授权状态以互物通服务端记录为准。',
          '激活设备将占用 License 设备名额；如需更换设备，请通过管理后台处理。',
          '我们可能根据法律法规或业务需要更新本协议，更新后继续使用即视为接受。',
          '如有疑问，请通过官网联系客服。',
        ],
      });
      wx.setNavigationBarTitle({ title: '用户协议' });
    } else {
      this.setData({
        title: '隐私政策',
        webUrl: 'https://88.huwutong.com/privacy',
        paragraphs: [
          '互物通重视您的隐私。本小程序可能收集：微信登录凭证（code/openid）、设备型号与系统信息（用于生成设备指纹）、您主动输入的 License Key 与设备名称。',
          '上述信息仅用于身份识别、授权查询与设备激活，不会向无关第三方出售。',
          '数据通过 HTTPS 传输，并按服务端安全策略存储。',
          '您可在「我的」中退出登录以清除本地登录态；历史查询记录保存在本机，可手动清空。',
          '完整隐私政策请参阅官网页面。',
        ],
      });
      wx.setNavigationBarTitle({ title: '隐私政策' });
    }
  },

  onOpenWeb() {
    const url = this.data.webUrl;
    wx.setClipboardData({
      data: url,
      success: () => {
        wx.showModal({
          title: '链接已复制',
          content: '请在手机浏览器中粘贴打开：\n' + url,
          showCancel: false,
        });
      },
    });
  },
});
