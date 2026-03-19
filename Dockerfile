# 1. 使用官方自带 Apache 的 PHP 8.2 镜像
FROM php:8.2-apache

# 2. 设置工作目录
WORKDIR /var/www/html

# 3. 将当前目录下的所有文件复制到容器中
COPY . /var/www/html/

# 4. 【核心修改】创建多级目录并授权
# -p 参数会自动创建不存在的父目录 (data) 和子目录 (sessions)
RUN mkdir -p /var/www/html/data/sessions && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 775 /var/www/html/data

# 5. 开启 Apache 的 Rewrite 模块
RUN a2enmod rewrite

# 6. 告知 Render 监听 80 端口
EXPOSE 80

# 7. 启动 Apache 服务
CMD ["apache2-foreground"]
